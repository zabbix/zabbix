//go:build !windows
// +build !windows

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

package dynamic

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"time"

	"github.com/go-zeromq/zmq4"
	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/dynamic"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/zbxerr"
)

// Plugin -
type Plugin struct {
	plugin.Base
	options      Options
	responseChan chan interface{}
	errChan      chan string
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
	// p.start(pluginPaths[key], 3*time.Second)
	p.responseChan = make(chan interface{})

	req := zmq4.NewReq(context.Background())
	defer req.Close()
	err = req.Dial(fmt.Sprintf("ipc:///tmp/%s", key))
	if err != nil {
		return
	}

	var reqData dynamic.Plugin
	reqData.Command = dynamic.Export
	reqData.Params = params

	reqBytes, err := json.Marshal(reqData)
	if err != nil {
		return
	}

	err = req.Send(zmq4.NewMsg(reqBytes))
	if err != nil {
		return
	}

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

// func (p *Plugin) start(path string, timeout time.Duration) {
// 	fmt.Println("starting", path)
// 	zbxcmd.Execute("/home/eriks/zabbix/src/go/dynamic/main", timeout, "")
// }

// func (p *Plugin) stop(path string) {
// 	//TODO: stop the binary
// }

func (p *Plugin) listen(req zmq4.Socket) {
	for {
		msg, err := req.Recv()
		if err != nil {
			log.Fatalf("could not recv response: %v", err)
			p.errChan <- err.Error()
			return
		}

		var resp dynamic.Plugin

		if err := json.Unmarshal(msg.Bytes(), &resp); err != nil {
			p.errChan <- err.Error()
			return
		}

		switch resp.RespType {
		case dynamic.Response:
			p.responseChan <- resp.Value
			return
		case dynamic.Request:
			fmt.Printf("Request: %v", resp.Value)
		case dynamic.Error:
			p.errChan <- resp.ErrMsg
			return
		default:
			p.errChan <- fmt.Sprintf("unknown response type:%x", resp.RespType)
		}
	}
}

func RegisterDynamicPlugins(paths []string) {
	var plugins []plugin.Accessor
	for _, p := range paths {
		// TODO  start plugins and get their type / stop plugin
		fmt.Println(p)
	}

	//TODO: for testing exporter
	plugins = append(plugins, &impl)
	pluginPaths["dynamic.test"] = "dynamic/main"

	//TODO: get plugin type / interface{} (EXPORTER WATCHER, etc)
	for _, p := range plugins {
		// get fields from dynamic plugin config ?
		plugin.RegisterMetrics(p, "Dynamic", "dynamic.test", "Exporter Test.")
	}
}

func init() {
	pluginPaths = make(map[string]string)
}
