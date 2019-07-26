/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package agent

import (
	"encoding/json"
	"io"
	"zabbix/internal/monitor"
	"zabbix/internal/plugin"
	"zabbix/pkg/itemutil"
	"zabbix/pkg/log"
)

type OutputController interface {
	// sets the default output writer
	SetOutput(w io.Writer)
	// flushes cache to the specified output writer
	FlushOutput(w io.Writer) (err error)
}

type ResultCache struct {
	input   chan interface{}
	output  io.Writer
	results []*plugin.Result
}

func (c *ResultCache) setOutput(w io.Writer) {
	c.output = w
}

type AgentData struct {
	Itemid      uint64  `json:"itemid"`
	LastLogsize *uint64 `json:"lastlogsize,omitempty"`
	Mtime       *int    `json:"mtime,omitempty"`
	State       *int    `json:"state,omitempty"`
	Value       *string `json:"value,omitempty"`
	Clock       int     `json:"clock,omitempty"`
	Ns          int     `json:"ns,omitempty"`
}

type AgentDataRequest struct {
	Request   string      `json:"request"`
	Data      []AgentData `json:"data"`
	Sessionid string      `json:"sessionid"`
	Host      string      `json:"host"`
	Version   string      `json:"version"`
}

func (c *ResultCache) flushOutput(w io.Writer) {

	log.Debugf("results %d", len(c.results))
	request := AgentDataRequest{
		Request:   "agent data",
		Data:      make([]AgentData, len(c.results)),
		Sessionid: "TODO",
		Host:      Options.Hostname,
		Version:   "TOOD",
	}

	for i, r := range c.results {
		d := &request.Data[i]
		d.Itemid = r.Itemid
		d.LastLogsize = r.LastLogsize
		d.Mtime = r.Mtime
		d.Clock = int(r.Ts.Unix())
		d.Ns = r.Ts.Nanosecond()
		if r.Error == nil {
			d.Value = r.Value
		} else {
			errmsg := r.Error.Error()
			d.Value = &errmsg
			state := itemutil.StateNotSupported
			d.State = &state
		}
	}
	var data []byte
	var err error

	if data, err = json.Marshal(&request); err != nil {
		log.Errf("cannot convert cached history to json: %s", err.Error())
		return
	}

	if w == nil {
		w = c.output
	}
	if _, err = w.Write(data); err != nil {
		log.Errf("cannot upload cached history to server: %s", err.Error())
		return
	}

	c.results = make([]*plugin.Result, 0)
}

func (c *ResultCache) write(result *plugin.Result) {
	c.results = append(c.results, result)
}

func (c *ResultCache) run() {
	defer log.PanicHook()
	log.Debugf("starting ResultCache")

	for {
		v := <-c.input
		if v == nil {
			break
		}
		switch v.(type) {
		case outputRequest:
			r := v.(outputRequest)
			c.setOutput(r.output)
		case flushRequest:
			r := v.(flushRequest)
			c.flushOutput(r.output)
		case *plugin.Result:
			r := v.(*plugin.Result)
			c.write(r)
		}
	}
	close(c.input)
	log.Debugf("Result cache has been stopped")
	monitor.Unregister()
}

func (c *ResultCache) Start() {
	c.input = make(chan interface{}, 100)
	c.results = make([]*plugin.Result, 0)
	monitor.Register()
	go c.run()
}

func (c *ResultCache) Stop() {
	c.input <- nil
}

type outputRequest struct {
	output io.Writer
}

func (c *ResultCache) SetOutput(w io.Writer) {
	c.input <- outputRequest{output: w}
}

type flushRequest struct {
	output io.Writer
}

func (c *ResultCache) FlushOutput(w io.Writer) {
	c.input <- flushRequest{output: w}
}

func (c *ResultCache) Flush() {
	c.FlushOutput(c.output)
}

func (c *ResultCache) Write(result *plugin.Result) {
	c.input <- result
}
