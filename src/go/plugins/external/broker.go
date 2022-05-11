/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
	"net"
	"time"

	"git.zabbix.com/ap/plugin-support/conf"
	"git.zabbix.com/ap/plugin-support/log"
	"git.zabbix.com/ap/plugin-support/plugin"
	"git.zabbix.com/ap/plugin-support/plugin/comms"
)

const queSize = 100

type pluginBroker struct {
	pluginName string
	socket     string
	timeout    time.Duration
	conn       net.Conn
	requests   map[uint32]chan interface{}
	errx       chan error
	log        chan interface{}
	// channel to handle agent->plugin requests
	tx chan *request
	// channel to handle plugin->agent requests/responses
	rx chan *request
}

type RequestWrapper struct {
	comms.Common
	comms.LogRequest
}

type request struct {
	id   uint32
	data interface{}
	out  chan interface{}
}

// p.socket = fmt.Sprintf("%s%d", socketBasePath, time.Now().UnixNano())

func (b *pluginBroker) SetPluginName(name string) {

}

func New(conn net.Conn, timeout time.Duration, socket string) *pluginBroker {
	broker := pluginBroker{
		socket:   socket,
		timeout:  timeout,
		conn:     conn,
		requests: make(map[uint32]chan interface{}),
		errx:     make(chan error, queSize),
		log:      make(chan interface{}),
		tx:       make(chan *request, queSize),
		rx:       make(chan *request, queSize),
	}

	return &broker
}

func (b *pluginBroker) handleConnection() {
	for {
		t, data, err := comms.Read(b.conn)
		if err != nil {
			return
		}

		var id uint32
		var resp interface{}

		switch t {
		case comms.RegisterResponseType:
			var reg comms.RegisterResponse
			err := json.Unmarshal(data, &reg)
			if err != nil {
				panic(
					fmt.Errorf(
						"failed to read register response for plugin %s, %s",
						b.pluginName,
						err.Error(),
					),
				)
			}

			id = reg.Id
			resp = reg

		case comms.LogRequestType:
			var log comms.LogRequest
			err := json.Unmarshal(data, &log)
			if err != nil {
				panic(
					fmt.Errorf(
						"failed to read log request response for plugin %s, %s",
						b.pluginName,
						err.Error(),
					),
				)
			}

			// plugin notifications don't have responses, so use 0 id
			resp = log

		case comms.ValidateResponseType:
			var valid comms.ValidateResponse
			err := json.Unmarshal(data, &valid)
			if err != nil {
				panic(
					fmt.Errorf(
						"failed to read validate response for plugin %s, %s",
						b.pluginName,
						err.Error(),
					),
				)
			}

			id = valid.Id
			resp = valid
		case comms.ExportResponseType:
			var export comms.ExportResponse
			err := json.Unmarshal(data, &export)
			if err != nil {
				panic(
					fmt.Errorf(
						"failed to read export response for plugin %s, %s",
						b.pluginName,
						err.Error(),
					),
				)
			}

			id = export.Id
			resp = export
		}

		b.rx <- &request{id: id, data: resp}
	}
}

func (b *pluginBroker) timeoutRequest(id uint32) {
	<-time.After(b.timeout)
	b.tx <- &request{id: id}
}

func (b *pluginBroker) runBackground() {
	var lastid uint32

	for {
		select {
		case r := <-b.rx:
			if r.id == 0 {
				// incoming plugin request, current only LogRequest
				b.log <- r.data
			} else {
				// response, forward data to the corresponding channel
				if o, ok := b.requests[r.id]; ok {
					o <- r.data
					close(o)
					delete(b.requests, r.id)
				}
			}

		case r := <-b.tx:
			if r.data == nil {
				if r.id == 0 {
					// stop request has null contents
					b.conn.Close()

					return
				}

				// timeout has id + nil data
				if o, ok := b.requests[r.id]; ok {
					o <- errors.New("timeout occurred")
					close(o)
					delete(b.requests, r.id)
				}
			} else {
				lastid++
				switch v := r.data.(type) {
				case *comms.ExportRequest:
					go b.timeoutRequest(lastid)
					v.Id = lastid
				case *comms.RegisterRequest:
					go b.timeoutRequest(lastid)
					v.Id = lastid
				case *comms.ValidateRequest:
					go b.timeoutRequest(lastid)
					v.Id = lastid
				case *comms.TerminateRequest:
					v.Id = lastid
				case *comms.ConfigureRequest:
					v.Id = lastid
				case *comms.StartRequest:
					v.Id = lastid
				}

				b.requests[lastid] = r.out
				err := comms.Write(b.conn, r.data)
				if err != nil {
					panic(
						fmt.Errorf(
							"failed to write request for plugin %s, %s",
							b.pluginName,
							err.Error(),
						),
					)
				}
			}
		}
	}
}

func (b *pluginBroker) handleLogs() {
	for u := range b.log {
		switch v := u.(type) {
		case comms.LogRequest:
			b.handleLog(v)
		default:
			log.Errf(`Failed to log message from plugins, unknown request type "%T"`, u)
		}
	}
}

func (b *pluginBroker) handleLog(l comms.LogRequest) {
	msg := l.Message
	if b.pluginName != "" {
		msg = fmt.Sprintf("[%s] %s", b.pluginName, msg)
	}

	switch l.Severity {
	case log.Info:
		log.Infof(msg)
	case log.Crit:
		log.Critf(msg)
	case log.Err:
		log.Errf(msg)
	case log.Warning:
		log.Warningf(msg)
	case log.Debug:
		log.Debugf(msg)
	case log.Trace:
		log.Tracef(msg)
	}
}

func (b *pluginBroker) run() {
	go b.handleLogs()
	go b.handleConnection()
	go b.runBackground()
}

func (b *pluginBroker) start() {
	r := request{
		data: &comms.StartRequest{
			Common: comms.Common{
				Type: comms.StartRequestType,
			},
		},
	}

	b.tx <- &r
}

func (b *pluginBroker) stop() {
	r := request{data: nil}
	b.tx <- &r
}

func (b *pluginBroker) export(key string, params []string) (*comms.ExportResponse, error) {
	data := comms.ExportRequest{
		Common: comms.Common{
			Type: comms.ExportRequestType,
		},
		Key:    key,
		Params: params,
	}

	r := request{
		data: &data,
		out:  make(chan interface{}),
	}

	b.tx <- &r
	u := <-r.out

	switch v := u.(type) {
	case comms.ExportResponse:
		return &v, nil
	case error:
		return nil, v
	}

	return nil, errors.New("unknown response")
}

func (b *pluginBroker) register() (*comms.RegisterResponse, error) {
	r := request{
		data: &comms.RegisterRequest{
			Common: comms.Common{
				Type: comms.RegisterRequestType,
			},
			Version: comms.Version,
		},
		out: make(chan interface{}),
	}

	b.tx <- &r
	u := <-r.out

	switch v := u.(type) {
	case comms.RegisterResponse:
		return &v, nil
	case error:
		return nil, v
	}

	return nil, errors.New("unknown response")
}

func (b *pluginBroker) configure(globalOptions *plugin.GlobalOptions, privateOptions interface{}) {
	r := request{
		data: &comms.ConfigureRequest{
			Common: comms.Common{
				Type: comms.ConfigureRequestType,
			},
			GlobalOptions:  globalOptions,
			PrivateOptions: privateOptions,
		},
	}

	b.tx <- &r
}

func (b *pluginBroker) validate(privateOptions interface{}) (*comms.ValidateResponse, error) {
	opts, ok := privateOptions.(*conf.Node)
	if !ok {
		return nil, fmt.Errorf("unsupported plugin options type %T", privateOptions)
	}
	r := request{
		data: &comms.ValidateRequest{
			Common: comms.Common{
				Type: comms.ValidateRequestType,
			},
			PrivateOptions: opts,
		},
		out: make(chan interface{}),
	}

	b.tx <- &r
	u := <-r.out

	switch v := u.(type) {
	case comms.ValidateResponse:
		return &v, nil
	case error:
		return nil, v
	}

	return nil, errors.New("unknown response")
}
