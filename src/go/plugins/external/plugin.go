/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

package external

import (
	"errors"
	"fmt"
	"net"
	"os/exec"
	"strconv"
	"strings"
	"sync"
	"time"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/plugin/comms"
)

var (
	_ plugin.Runner       = (*Plugin)(nil)
	_ plugin.Configurator = (*Plugin)(nil)
	_ plugin.Exporter     = (*Plugin)(nil)
)

// startLock is used to ensure that only one plugin is started at a time.
// view startPlugin method for more details.
var startLock sync.Mutex //nolint:gochecknoglobals

// Plugin represents an external plugin.
type Plugin struct {
	plugin.Base
	Path string

	name          string // name of plugin
	socket        string
	interfaces    uint32
	listener      net.Listener
	timeout       time.Duration
	cmd           *exec.Cmd
	cmdWait       chan error    // cmd.Wait() result
	pluginStopped chan struct{} // triggered by plugin.Stop() request
	broker        *pluginBroker
}

// NewPlugin created a new external plugin accessor instance.
func NewPlugin(
	name, path, socket string,
	timeout time.Duration,
	listener net.Listener,
) *Plugin {
	base := plugin.Base{
		Logger: log.New(name),
	}
	base.SetExternal(true)

	return &Plugin{
		Base:          base,
		name:          name,
		Path:          path,
		socket:        socket,
		listener:      listener,
		timeout:       timeout,
		cmdWait:       make(chan error),
		pluginStopped: make(chan struct{}),
	}
}

// RegisterMetrics starts the plugin process, sends register and validate
// (if supported by plugin) requests and stops plugin.
func (p *Plugin) RegisterMetrics(config any) error {
	pluginExit, err := p.startPlugin(true)
	if err != nil {
		return errs.Wrap(err, "failed to start plugin")
	}

	jErr := errors.Join(
		func() error {
			err = p.register()
			if err != nil {
				return errs.Wrap(err, "failed to register plugin")
			}

			defer p.Stop()

			if comms.ImplementsConfigurator(p.interfaces) {
				err := p.Validate(config)
				if err != nil {
					return errs.Wrap(err, "failed to validate plugin")
				}
			}

			return nil
		}(),
		<-pluginExit, // wait for plugin to exit
	)
	if jErr != nil {
		return errs.Wrap(jErr, "failed plugin registration")
	}

	return nil
}

// register sends a register request to the plugin and processes the response.
func (p *Plugin) register() error {
	p.Debugf("sending register request to plugin %q", p.name)

	resp, err := p.broker.register()
	if err != nil {
		return errs.Wrap(err, "failed to send register request to plugin")
	}

	if resp.Error != "" {
		return errs.New(resp.Error)
	}

	if resp.Name != p.name {
		return errs.Errorf(
			"mismatch plugin names %s and %s, with plugin path %s",
			p.name, resp.Name, p.Path,
		)
	}

	p.interfaces = resp.Interfaces

	p.Debugf(
		"plugin implements configurator: %t, exporter: %t, runner: %t",
		comms.ImplementsConfigurator(p.interfaces),
		comms.ImplementsExporter(p.interfaces),
		comms.ImplementsRunner(p.interfaces),
	)

	err = plugin.RegisterMetrics(p, p.name, resp.Metrics...)
	if err != nil {
		return errs.Wrap(err, "failed to register metrics")
	}

	return nil
}

// startPlugin starts the plugin process for operation stage and creates a
// request broker.
func (p *Plugin) startPlugin(initial bool) (<-chan error, error) {
	// if multiple plugins are started simultaneously, it would be impossible
	// to determine which connection belongs to which plugin, hence a lock is
	// needed to ensure that at a single moment in time only one plugin gets
	// started and the connection created belongs to that one plugin.
	startLock.Lock()
	defer startLock.Unlock()

	p.Debugf(
		"starting process %q",
		strings.Join(
			[]string{p.Path, p.socket, strconv.FormatBool(initial)}, " ",
		),
	)

	p.cmd = exec.Command(p.Path, p.socket, strconv.FormatBool(initial)) //nolint:gosec

	err := p.cmd.Start()
	if err != nil {
		return nil, errs.Wrapf(err, "failed to start plugin process %q", p.Path)
	}

	go func() {
		p.Base.Debugf("plugin process exited")
		p.cmdWait <- p.cmd.Wait()
	}()

	conn, err := getConnection(p.listener, p.timeout)
	if err != nil {
		killErr := p.killPlugin()
		if killErr != nil {
			p.Errf("failed to kill plugin %s: %s", p.Path, killErr.Error())
		}

		return nil, errs.Wrapf(
			err, "failed to create connection with plugin %s", p.Path,
		)
	}

	p.broker = newBroker(p.name, conn, p.timeout, p.socket)

	pluginExit := make(chan error)

	go func() {
		defer func() {
			p.Debugf("stoping communications broker")
			p.broker.stop()
		}()

		select {
		case err := <-p.cmdWait:
			if err != nil {
				pluginExit <- errs.Wrap(err, "plugin exited unexpectedly")

				return
			}

			pluginExit <- errs.New("plugin exited unexpectedly")
		case <-p.pluginStopped:
			t := time.NewTimer(p.timeout)
			defer t.Stop()

			select {
			case <-t.C:
				err := p.killPlugin()
				if err != nil {
					p.Errf("failed to kill plugin %s: %s", p.Path, err.Error())
				}

				pluginExit <- errs.New("timeout while waiting for plugin process to exit, killed process")
			case <-p.cmdWait:
				p.Infof("plugin %q process exited", p.Path)

				pluginExit <- nil
			}
		}
	}()

	return pluginExit, nil
}

func (p *Plugin) killPlugin() error {
	if p.cmd == nil || p.cmd.Process == nil {
		return nil
	}

	err := p.cmd.Process.Kill()
	if err != nil {
		return errs.Wrapf(err, "failed to kill plugin %q process", p.Path)
	}

	err = <-p.cmdWait
	if err != nil {
		return errs.Wrapf(err, "plugin %q process exited with error", p.Path)
	}

	return nil
}

// Start implements the Runner interface for this external plugin wrapper.
// `start` request is only sent if the plugin also implements the Runner
// interface.
func (p *Plugin) Start() {
	if comms.ImplementsRunner(p.interfaces) {
		p.broker.start()
	}
}

// Stop sends a `terminate` request to the plugin process.
func (p *Plugin) Stop() {
	p.Debugf("sending terminate request")

	p.pluginStopped <- struct{}{}

	err := comms.Write(
		p.broker.conn,
		comms.TerminateRequest{
			Common: comms.Common{
				Id:   comms.NonRequiredID,
				Type: comms.TerminateRequestType,
			},
		},
	)
	if err != nil {
		panic(fmt.Sprintf(
			"failed to send stop request to plugin %s, %s", p.Path, err.Error(),
		))
	}
}

// Configure starts the plugin process for operation stage. Sends a register
// request if the plugin implements the Configurator interface.
func (p *Plugin) Configure(
	globalOptions *plugin.GlobalOptions, privateOptions any,
) {
	pluginExit, err := p.startPlugin(false)
	if err != nil {
		panic(err)
	}

	go func() {
		if err := <-pluginExit; err != nil {
			//nolint:godox
			// FIXME: Will be fixed in DEV-3709.
			// Need to notify the owner of Plugin struct that the plugin has
			// failed. Then it can further notify the run func from main.
			panic(err)
		}
	}()

	p.broker.pluginName = p.Name()

	if comms.ImplementsConfigurator(p.interfaces) {
		p.broker.configure(globalOptions, privateOptions)
	}
}

// Validate sends a `validate` request to the plugin.
func (p *Plugin) Validate(privateOptions any) error {
	p.Debugf("sending validate request")

	resp, err := p.broker.validate(privateOptions)
	if err != nil {
		return errs.Wrap(err, "failed to send validate request to plugin")
	}

	if resp.Error != "" {
		return errs.New(resp.Error)
	}

	return nil
}

// Export sends an `export` request to the plugin.
func (p *Plugin) Export(key string, params []string, _ plugin.ContextProvider) (any, error) {
	resp, err := p.broker.export(key, params)
	if err != nil {
		return nil, err
	}

	if resp.Error == "" {
		return resp.Value, nil
	}

	return nil, errs.New(resp.Error)
}

func getConnection(
	listener net.Listener, timeout time.Duration,
) (net.Conn, error) {
	var (
		connC = make(chan net.Conn)
		errC  = make(chan error)
		t     = time.NewTimer(timeout)
	)

	defer func() {
		close(connC)
		close(errC)
		t.Stop()
	}()

	go func() {
		conn, err := listener.Accept()
		if err != nil {
			errC <- err
		}

		connC <- conn
	}()

	select {
	case conn := <-connC:
		return conn, nil
	case err := <-errC:
		return nil, err
	case <-t.C:
		return nil, errs.Errorf(
			"failed to get connection within the time limit %d", timeout,
		)
	}
}
