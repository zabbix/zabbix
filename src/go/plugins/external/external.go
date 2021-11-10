/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net"
	"os"
	"os/exec"
	"strconv"
	"strings"
	"sync"
	"time"

	"zabbix.com/internal/agent"
	"zabbix.com/pkg/log"
	"zabbix.com/pkg/plugin/shared"

	"zabbix.com/pkg/plugin"
)

// Plugin -
type externaltHandler struct {
	socketBasePath string
	registerChan   map[uint32]chan shared.RegisterResponse
	validateChan   map[uint32]chan shared.ValidateResponse
	exportChan     map[uint32]chan shared.ExportResponse
	collectChan    map[uint32]chan shared.CollectResponse
	periodChan     map[uint32]chan shared.PeriodResponse
	logChan        chan shared.LogRequest
	errChan        chan error
	timeout        time.Duration
}

type Plugin struct {
	plugin.Base
	Path           string
	Socket         string
	Params         []string
	Interfaces     uint32
	initial        bool
	conn           net.Conn
	listener       net.Listener
	globalOptions  *plugin.GlobalOptions
	privateOptions interface{}
	startWg        sync.WaitGroup
}

var handler externaltHandler

func init() {
	handler.errChan = make(chan error)
	handler.logChan = make(chan shared.LogRequest)
	handler.registerChan = make(map[uint32]chan shared.RegisterResponse)
	handler.validateChan = make(map[uint32]chan shared.ValidateResponse)
	handler.exportChan = make(map[uint32]chan shared.ExportResponse)
	handler.collectChan = make(map[uint32]chan shared.CollectResponse)
	handler.periodChan = make(map[uint32]chan shared.PeriodResponse)
}

func InitExternalPlugins(options *agent.AgentOptions) error {
	setConfigValues(options.ExternalPluginsSocket)

	go startLogListener()

	for _, p := range options.ExternalPlugins {
		accessor := &Plugin{}
		accessor.Path = p
		accessor.SetExternal(true)

		name, err := accessor.initExternalPlugin(options)
		if err != nil {
			return err
		}

		plugin.RegisterMetrics(accessor, name, accessor.Params...)
	}

	return nil
}

func ErrListener() (err error) {
	err = <-handler.errChan
	return
}

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	req := shared.CreateExportRequest(key, params)
	handler.exportChan[req.Id] = make(chan shared.ExportResponse)

	err = shared.Write(p.conn, req)
	if err != nil {
		handler.errChan <- err
		return
	}

	select {
	case response := <-handler.exportChan[req.Id]:
		if response.Error != "" {
			err = errors.New(response.Error)
			break
		}
		result = response.Value
	case <-time.After(handler.timeout):
		handler.errChan <- errors.New("failed to receive Export response")
	}

	return
}

// func (p *Plugin) Collect() (err error) {
// 	if !shared.ImplementsCollector(p.Interfaces) {
// 		return nil
// 	}

// 	req := shared.CreateCollectRequest()
// 	handler.collectChan[req.Id] = make(chan shared.CollectResponse)

// 	err = shared.Write(p.conn, req)
// 	if err != nil {
// 		handler.errChan <- err
// 		return
// 	}

// 	select {
// 	case response := <-handler.collectChan[req.Id]:
// 		if response.Error != "" {
// 			err = errors.New(response.Error)
// 		}
// 	case <-time.After(handler.timeout):
// 		handler.errChan <- errors.New("failed to receive Collect response")
// 	}

// 	return
// }

// func (p *Plugin) Period() int {
// 	if !shared.ImplementsCollector(p.Interfaces) {
// 		return 0
// 	}

// 	req := shared.CreatePeriodRequest()
// 	handler.periodChan[req.Id] = make(chan shared.PeriodResponse)

// 	err := shared.Write(p.conn, req)
// 	if err != nil {
// 		handler.errChan <- err
// 		return 0
// 	}

// 	select {
// 	case response := <-handler.periodChan[req.Id]:
// 		return response.Period
// 	case <-time.After(handler.timeout):
// 		handler.errChan <- errors.New("failed to receive Export response")
// 	}

// 	return 0
// }

func (p *Plugin) Start() {
	err := exec.Command(p.Path, p.Socket, strconv.FormatBool(p.initial)).Start()
	if err != nil {
		handler.errChan <- fmt.Errorf("failed to start external plugin %s, %s", p.Path, err.Error())
		return
	}

	if err := os.RemoveAll(p.Socket); err != nil {
		handler.errChan <- fmt.Errorf("failed to drop external plugin %s socket, with path %s, %s", p.Path, p.Socket, err.Error())
		return
	}

	p.listener, err = net.Listen("unix", p.Socket)
	if err != nil {
		handler.errChan <- fmt.Errorf("failed to listen on external plugin %s socket path %s, %s", p.Path, p.Socket, err.Error())
		return
	}

	p.conn, err = getConnection(p.listener, handler.timeout)
	if err != nil {
		handler.errChan <- fmt.Errorf("failed to create connection with external plugin %s, %s", p.Path, err.Error())
		return
	}

	go startPluginListener(p.conn)

	if !p.initial {
		if shared.ImplementsConfigurator(p.Interfaces) {
			err := shared.Write(p.conn, shared.CreateConfigurateRequest(p.globalOptions, p.privateOptions))
			if err != nil {
				handler.errChan <- fmt.Errorf("failed to configurate external plugin %s, %s", p.Path, err.Error())
			}
		}
		return
	}

	p.startWg.Done()
}

func (p *Plugin) Stop() {
	err := shared.Write(p.conn, shared.CreateTerminateRequest())
	if err != nil {
		handler.errChan <- fmt.Errorf("failed to send stop request to external plugin %s, %s", p.Path, err.Error())
		return
	}

	err = p.listener.Close()
	if err != nil {
		handler.errChan <- fmt.Errorf("failed to close listener for external plugin %s, %s", p.Path, err.Error())
		return
	}

	if err := os.RemoveAll(p.Socket); err != nil {
		handler.errChan <- fmt.Errorf("failed to drop external plugin %s socket, with path %s, %s", p.Path, p.Socket, err.Error())
		return
	}
}

func setConfigValues(sockBasePath string) {
	if sockBasePath == "" {
		sockBasePath = "/tmp/plugins/"

	} else if !strings.HasSuffix(sockBasePath, "/") {
		sockBasePath += "/"
	}

	handler.socketBasePath = sockBasePath

	if agent.Options.ExternalPluginTimeout == 0 {
		handler.timeout = time.Second * time.Duration(agent.Options.Timeout)
		return
	}

	handler.timeout = time.Second * time.Duration(agent.Options.ExternalPluginTimeout)
}

func (p *Plugin) initExternalPlugin(options *agent.AgentOptions) (name string, err error) {
	p.initial = true
	p.createSocket(handler.socketBasePath)
	p.startWg.Add(1)
	p.Start()
	p.startWg.Wait()

	var resp shared.RegisterResponse
	resp, err = register(p.conn)
	if err != nil {
		return
	}

	if resp.Error != "" {
		p.Stop()
		handler.errChan <- errors.New(resp.Error)
	}

	name = resp.Name

	p.Interfaces = resp.Interfaces
	p.Params = resp.Metrics

	if shared.ImplementsConfigurator(p.Interfaces) {
		if err = validate(p.conn, options.Plugins[name]); err != nil {
			return
		}
	}

	p.Stop()
	p.initial = false
	return
}

func (p *Plugin) createSocket(socketBasePath string) {
	p.Socket = fmt.Sprintf("%s%d", socketBasePath, time.Now().UnixNano())
}

func logMessage(req shared.LogRequest) {
	switch req.Severity {
	case log.Info:
		log.Infof(req.Message)
	case log.Crit:
		log.Critf(req.Message)
	case log.Err:
		log.Errf(req.Message)
	case log.Warning:
		log.Warningf(req.Message)
	case log.Debug:
		log.Debugf(req.Message)
	case log.Trace:
		log.Tracef(req.Message)
	}
}

func register(conn net.Conn) (response shared.RegisterResponse, err error) {
	req := shared.CreateRegisterRequest()
	handler.registerChan[req.Id] = make(chan shared.RegisterResponse)

	err = shared.Write(conn, req)
	if err != nil {
		return shared.RegisterResponse{}, err
	}

	select {
	case response = <-handler.registerChan[req.Id]:
		return
	case <-time.After(handler.timeout):
		return shared.RegisterResponse{}, fmt.Errorf("failed to receive register response")
	}
}

func validate(conn net.Conn, options interface{}) (err error) {
	req := shared.CreateValidateRequest(options)
	handler.validateChan[req.Id] = make(chan shared.ValidateResponse)

	err = shared.Write(conn, req)
	if err != nil {
		return
	}

	select {
	case response := <-handler.validateChan[req.Id]:
		if response.Error != "" {
			return errors.New(response.Error)
		}

		return
	case <-time.After(handler.timeout):
		return fmt.Errorf("failed to receive register response for p")
	}
}

func startPluginListener(conn net.Conn) {
	for {
		t, data, err := shared.Read(conn)
		if err != nil {
			if err == io.EOF {
				return
			}

			handler.errChan <- err
		}

		go handleRequest(t, data)
	}
}

func startLogListener() {
	for log := range handler.logChan {
		logMessage(log)
	}
}

func handleRequest(t uint32, data []byte) {
	switch t {
	case shared.RegisterResponseType:
		var resp shared.RegisterResponse
		err := json.Unmarshal(data, &resp)
		if err != nil {
			handler.errChan <- err
			return
		}

		handler.registerChan[resp.Id] <- resp
		close(handler.registerChan[resp.Id])
		return
	case shared.ValidateResponseType:
		var resp shared.ValidateResponse
		err := json.Unmarshal(data, &resp)
		if err != nil {
			handler.errChan <- err
			return
		}

		handler.validateChan[resp.Id] <- resp
		close(handler.validateChan[resp.Id])
		return
	case shared.ExportResponseType:
		var resp shared.ExportResponse
		err := json.Unmarshal(data, &resp)
		if err != nil {
			handler.errChan <- err
			return
		}

		handler.exportChan[resp.Id] <- resp
		close(handler.exportChan[resp.Id])
		return
	// case shared.CollectorResponseType:
	// 	var resp shared.CollectResponse
	// 	err := json.Unmarshal(data, &resp)
	// 	if err != nil {
	// 		handler.errChan <- err
	// 		return
	// 	}

	// 	handler.collectChan[resp.Id] <- resp
	// 	close(handler.collectChan[resp.Id])
	// 	return
	// case shared.PeriodResponseType:
	// 	var resp shared.PeriodResponse
	// 	err := json.Unmarshal(data, &resp)
	// 	if err != nil {
	// 		handler.errChan <- err
	// 		return
	// 	}

	// 	handler.periodChan[resp.Id] <- resp
	// 	close(handler.periodChan[resp.Id])
	// 	return
	case shared.LogRequestType:
		var req shared.LogRequest
		err := json.Unmarshal(data, &req)
		if err != nil {
			handler.errChan <- err
			return
		}

		handler.logChan <- req
		return
	}
}

func getConnection(listener net.Listener, timeout time.Duration) (conn net.Conn, err error) {
	connChan := make(chan net.Conn)
	errChan := make(chan error)

	go listen(listener, connChan, errChan)

	select {
	case conn = <-connChan:
	case err = <-errChan:
	case <-time.After(timeout):
		err = fmt.Errorf("failed to get connection within the time limit %d", timeout)
	}

	return
}

func listen(listener net.Listener, ch chan<- net.Conn, errCh chan<- error) {
	conn, err := listener.Accept()
	if err != nil {
		errCh <- err
	}

	ch <- conn
}
