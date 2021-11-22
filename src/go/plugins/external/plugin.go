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
	"errors"
	"fmt"
	"net"
	"os/exec"
	"strconv"
	"time"

	"zabbix.com/pkg/shared"

	"zabbix.com/pkg/plugin"
)

// Plugin -
type Plugin struct {
	plugin.Base
	Path       string
	Socket     string
	Params     []string
	Interfaces uint32
	Initial    bool
	Listener   net.Listener
	Timeout    time.Duration
	broker     *pluginBroker
}

func (p *Plugin) Register() (response *shared.RegisterResponse, err error) {
	return p.broker.register()
}

func (p *Plugin) Start() {
	err := exec.Command(p.Path, p.Socket, strconv.FormatBool(p.Initial)).Start()
	if err != nil {
		panic(fmt.Sprintf("failed to start external plugin %s, %s", p.Path, err.Error()))
	}

	conn, err := getConnection(p.Listener, p.Timeout)
	if err != nil {
		panic(fmt.Sprintf("failed to create connection with external plugin %s, %s", p.Path, err.Error()))
	}

	p.broker = New(conn, p.Timeout, p.Socket)

	p.broker.run()
}

func (p *Plugin) Stop() {
	err := shared.Write(
		p.broker.conn,
		shared.TerminateRequest{
			Common: shared.Common{
				Id:   shared.NonRequiredID,
				Type: shared.TerminateRequestType},
		},
	)

	if err != nil {
		panic(fmt.Sprintf("failed to send stop request to external plugin %s, %s", p.Path, err.Error()))
	}

	p.broker.stop()
}

func (p *Plugin) Configure(globalOptions *plugin.GlobalOptions, privateOptions interface{}) {
	p.broker.configure(globalOptions, privateOptions)
}

func (p *Plugin) Validate(privateOptions interface{}) error {
	resp, err := p.broker.validate(privateOptions)
	if err != nil {
		return err
	}

	if resp.Error == "" {
		return nil
	}

	return errors.New(resp.Error)
}

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	resp, err := p.broker.export(key, params)
	if err != nil {
		return nil, err
	}

	if resp.Error == "" {
		return resp.Value, nil
	}

	return nil, errors.New(resp.Error)
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
