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

package resultcache

import (
	"crypto/md5"
	"encoding/binary"
	"encoding/hex"
	"encoding/json"
	"io"
	"time"
	"zabbix/internal/agent"
	"zabbix/internal/monitor"
	"zabbix/internal/plugin"
	"zabbix/pkg/itemutil"
	"zabbix/pkg/log"
)

type ResultCache struct {
	input      chan interface{}
	output     Uploader
	results    []*plugin.Result
	token      string
	lastDataID uint64
	clientID   uint64
	lastError  error
}

type AgentData struct {
	Id          uint64  `json:"id"`
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

type Uploader interface {
	io.Writer
	Addr() (s string)
}

func (c *ResultCache) flushOutput(u Uploader) {
	log.Debugf("[%d] upload history data, %d value(s)", c.clientID, len(c.results))
	if len(c.results) == 0 {
		return
	}

	request := AgentDataRequest{
		Request:   "agent data",
		Data:      make([]AgentData, len(c.results)),
		Sessionid: c.token,
		Host:      agent.Options.Hostname,
		Version:   "TODO",
	}

	lastDataID := c.lastDataID

	for i, r := range c.results {
		d := &request.Data[i]
		lastDataID++
		d.Id = lastDataID
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
		log.Errf("[%d] cannot convert cached history to json: %s", c.clientID, err.Error())
		return
	}

	if u == nil {
		u = c.output
	}
	if _, err = u.Write(data); err != nil {
		if c.lastError == nil || err.Error() != c.lastError.Error() {
			log.Warningf("[%d] history upload to [%s] started to fail: %s", c.clientID, u.Addr(), err)
			c.lastError = err
		}
		return
	}

	if c.lastError != nil {
		log.Warningf("[%d] history upload to [%s] is working again", c.clientID, u.Addr())
		c.lastError = nil
	}

	c.lastDataID = lastDataID
	c.results = make([]*plugin.Result, 0)
}

func (c *ResultCache) write(result *plugin.Result) {
	c.results = append(c.results, result)
}

func (c *ResultCache) run() {
	defer log.PanicHook()
	log.Debugf("[%d] starting result cache", c.clientID)

	for {
		v := <-c.input
		if v == nil {
			break
		}
		switch v.(type) {
		case Uploader:
			c.flushOutput(v.(Uploader))
		case *plugin.Result:
			r := v.(*plugin.Result)
			c.write(r)
		}
	}
	close(c.input)
	log.Debugf("[%d] result cache has been stopped", c.clientID)
	monitor.Unregister()
}

func newToken() string {
	h := md5.New()
	_ = binary.Write(h, binary.LittleEndian, time.Now().UnixNano())
	return hex.EncodeToString(h.Sum(nil))
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

func NewActive(clientid uint64, output Uploader) *ResultCache {
	return &ResultCache{clientID: clientid, output: output, token: newToken()}
}

func NewPassive(clientid uint64) *ResultCache {
	return &ResultCache{clientID: clientid, token: newToken()}
}

func (c *ResultCache) FlushOutput(u Uploader) {
	c.input <- u
}

func (c *ResultCache) Flush() {
	// only active connections with output set can be flushed without specifying output
	if c.output != nil {
		c.FlushOutput(c.output)
	}
}

func (c *ResultCache) Write(result *plugin.Result) {
	c.input <- result
}
