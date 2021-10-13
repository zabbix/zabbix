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
	"context"
	"encoding/json"
	"fmt"
	"log"
	"os/exec"
	"path/filepath"
	"time"

	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/shared"

	"github.com/go-zeromq/zmq4"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/zbxerr"
)

// Plugin -
type Plugin struct {
	plugin.Base
	options      Options
	Type         int
	responseChan chan interface{}
	errChan      chan string
	Params       []string
}

// Options -
type Options struct {
	Timeout int
	Plugins []string
}

var impl Plugin

var pluginPaths map[string]string

func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	if err := conf.Unmarshal(options, &p.options); err != nil {
		p.Errf("cannot unmarshal configuration options: %s", err)
	}

	if p.options.Timeout == 0 {
		p.options.Timeout = global.Timeout
	}
}

// Validate -
func (p *Plugin) Validate(options interface{}) error { return nil }

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	p.responseChan = make(chan interface{})

	req := zmq4.NewReq(context.Background())
	sendRequest(pluginPaths[key], shared.Export, params, req)
	defer req.Close()

	go p.listen(req)

	select {
	case result = <-p.responseChan:
		return
	case errMsg := <-p.errChan:
		return nil, zbxerr.New(errMsg)
	case <-time.After(time.Duration(p.options.Timeout) * time.Second):
		return nil, zbxerr.ErrorCannotFetchData
	}
}

func (p *Plugin) listen(req zmq4.Socket) {
	for {
		msg, err := req.Recv()
		if err != nil {
			log.Fatalf("could not recv response: %v", err)
			p.errChan <- err.Error()
			return
		}

		var resp shared.Plugin

		if err := json.Unmarshal(msg.Bytes(), &resp); err != nil {
			p.errChan <- err.Error()
			return
		}

		switch resp.RespType {
		case shared.Response:
			p.responseChan <- resp.Value
			return
		case shared.Request:
			fmt.Printf("Request: %v", resp.Value)
		case shared.Error:
			p.errChan <- resp.ErrMsg
			return
		default:
			p.errChan <- fmt.Sprintf("unknown response type:%x", resp.RespType)
		}
	}
}

func RegisterDynamicPlugins(paths []string, timeout time.Duration) error {
	for _, p := range paths {

		cmd := Start(p)
		accessor, err := getPlugin(p)
		if err != nil {
			return err
		}

		for i := 0; i < len(accessor.Params); i += 2 {
			pluginPaths[accessor.Params[i]] = p
		}

		plugin.RegisterMetrics(&accessor, accessor.Name(), accessor.Params...)
		err = cmd.Process.Kill()
		if err != nil {
			return err
		}
	}

	return nil
}

func init() {
	pluginPaths = make(map[string]string)
}

func Start(path string) *exec.Cmd {
	cmd := exec.Command(path)
	cmd.Start()
	return cmd
}

func getPlugin(path string) (Plugin, error) {
	req := zmq4.NewReq(context.Background())
	defer req.Close()

	if err := sendRequest(path, shared.Metrics, nil, req); err != nil {
		return Plugin{}, err
	}

	msg, err := req.Recv()
	if err != nil {
		return Plugin{}, err
	}

	var resp shared.Plugin
	if err := json.Unmarshal(msg.Bytes(), &resp); err != nil {
		return Plugin{}, err
	}

	switch resp.RespType {
	case shared.Metrics:
		var p Plugin
		p.Type = resp.Supported
		p.Params = resp.Params
		p.SetCapacity(1)

		return p, err
	default:
		return Plugin{}, fmt.Errorf("unknown response type:%x", resp.RespType)
	}
}

func sendRequest(path string, command int, params []string, sock zmq4.Socket) error {
	err := sock.Dial(fmt.Sprintf("ipc:///tmp/%s.sock", filepath.Base(path)))
	if err != nil {
		return err
	}

	reqData := shared.Plugin{Command: command, Params: params}
	reqBytes, err := json.Marshal(reqData)
	if err != nil {
		return err
	}

	err = sock.Send(zmq4.NewMsg(reqBytes))
	if err != nil {
		return err
	}

	return nil
}
