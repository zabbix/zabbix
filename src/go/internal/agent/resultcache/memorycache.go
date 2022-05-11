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
package resultcache

import (
	"encoding/json"
	"errors"
	"reflect"
	"sync/atomic"
	"time"

	"git.zabbix.com/ap/plugin-support/log"
	"git.zabbix.com/ap/plugin-support/plugin"
	"zabbix.com/internal/agent"
	"zabbix.com/internal/monitor"
	"zabbix.com/pkg/itemutil"
	"zabbix.com/pkg/version"
)

type MemoryCache struct {
	*cacheData
	results         []*AgentData
	maxBufferSize   int32
	totalValueNum   int32
	persistValueNum int32
}

func (c *MemoryCache) upload(u Uploader) (err error) {
	if len(c.results) == 0 {
		return
	}

	c.Debugf("upload history data, %d/%d value(s)", len(c.results), cap(c.results))

	request := AgentDataRequest{
		Request: "agent data",
		Data:    c.results,
		Session: c.token,
		Host:    u.Hostname(),
		Version: version.Short(),
	}

	var data []byte

	if data, err = json.Marshal(&request); err != nil {
		c.Errf("cannot convert cached history to json: %s", err.Error())
		return
	}

	timeout := len(c.results) * c.timeout
	if timeout > 60 {
		timeout = 60
	}
	if errs := u.Write(data, time.Duration(timeout)*time.Second); errs != nil {
		if !reflect.DeepEqual(errs, c.lastErrors) {
			for i := 0; i < len(errs); i++ {
				c.Warningf("%s", errs[i])
			}
			c.Warningf("history upload to [%s] [%s] started to fail", u.Addr(), u.Hostname())
			c.lastErrors = errs
		}

		return errors.New("history upload failed")
	}

	if c.lastErrors != nil {
		c.Warningf("history upload to [%s] [%s] is working again", u.Addr(), u.Hostname())
		c.lastErrors = nil
	}

	// clear results slice to ensure that the data is garbage collected
	c.results[0] = nil
	for i := 1; i < len(c.results); i *= 2 {
		copy(c.results[i:], c.results[:i])
	}
	c.results = c.results[:0]

	c.totalValueNum = 0
	c.persistValueNum = 0
	return
}

func (c *MemoryCache) flushOutput(u Uploader) {
	if c.retry != nil {
		c.retry.Stop()
		c.retry = nil
	}

	if c.upload(u) != nil && u.CanRetry() {
		c.retry = time.AfterFunc(UploadRetryInterval, func() { c.Upload(u) })
	}
}

// addResult appends received result at the end of results slice
func (c *MemoryCache) addResult(result *AgentData) {
	full := c.persistValueNum >= c.maxBufferSize/2 || c.totalValueNum >= c.maxBufferSize
	c.results = append(c.results, result)
	c.totalValueNum++
	if result.persistent {
		c.persistValueNum++
	}

	if c.persistValueNum >= c.maxBufferSize/2 || c.totalValueNum >= c.maxBufferSize {
		if !full && c.uploader != nil {
			c.flushOutput(c.uploader)
		}
	}
}

// insertResult attempts to insert the received result into results slice by replacing existing value.
// If no appropriate target was found it calls addResult to append value.
func (c *MemoryCache) insertResult(result *AgentData) {
	index := -1
	if !result.persistent {
		for i, r := range c.results {
			if r.Itemid == result.Itemid {
				c.Debugf("cache is full, replacing oldest value for itemid:%d", r.Itemid)
				index = i
				break
			}
		}
	}
	if index == -1 && (!result.persistent || c.persistValueNum < c.maxBufferSize/2) {
		for i, r := range c.results {
			if !r.persistent {
				if result.persistent {
					c.persistValueNum++
				}
				c.Debugf("cache is full, removing oldest value for itemid:%d", r.Itemid)
				index = i
				break
			}
		}
	}
	if index == -1 {
		c.Warningf("cache is full and cannot cannot find a value to replace, adding new instead")
		c.addResult(result)
		return
	}

	copy(c.results[index:], c.results[index+1:])
	c.results[len(c.results)-1] = result
}

func (c *MemoryCache) write(r *plugin.Result) {
	c.lastDataID++
	var value *string
	var state *int
	if r.Error == nil {
		value = r.Value
	} else {
		errmsg := r.Error.Error()
		value = &errmsg
		tmp := itemutil.StateNotSupported
		state = &tmp
	}

	var clock, ns int
	if !r.Ts.IsZero() {
		clock = int(r.Ts.Unix())
		ns = r.Ts.Nanosecond()
	}

	data := &AgentData{
		Id:             c.lastDataID,
		Itemid:         r.Itemid,
		LastLogsize:    r.LastLogsize,
		Mtime:          r.Mtime,
		Clock:          clock,
		Ns:             ns,
		Value:          value,
		State:          state,
		EventSource:    r.EventSource,
		EventID:        r.EventID,
		EventSeverity:  r.EventSeverity,
		EventTimestamp: r.EventTimestamp,
		persistent:     r.Persistent,
	}

	if c.totalValueNum >= c.maxBufferSize {
		c.insertResult(data)
	} else {
		c.addResult(data)
	}
}

func (c *MemoryCache) run() {
	defer log.PanicHook()
	c.Debugf("starting memory cache")

	for {
		u := <-c.input
		if u == nil {
			break
		}
		switch v := u.(type) {
		case Uploader:
			c.flushOutput(v)
		case *plugin.Result:
			c.write(v)
		case *agent.AgentOptions:
			c.updateOptions(v)
		}
	}
	c.Debugf("memory cache has been stopped")
	monitor.Unregister(monitor.Output)
}

func (c *MemoryCache) updateOptions(options *agent.AgentOptions) {
	c.maxBufferSize = int32(options.BufferSize)
	c.timeout = options.Timeout
}

func (c *MemoryCache) init(options *agent.AgentOptions) {
	c.updateOptions(options)
	c.results = make([]*AgentData, 0, c.maxBufferSize)
}

func (c *MemoryCache) Start() {
	// register with secondary group to stop result cache after other components are stopped
	monitor.Register(monitor.Output)
	go c.run()
}

func (c *MemoryCache) SlotsAvailable() int {
	slots := atomic.LoadInt32(&c.maxBufferSize) - atomic.LoadInt32(&c.totalValueNum)
	if slots < 0 {
		slots = 0
	}

	return int(slots)
}

func (c *MemoryCache) PersistSlotsAvailable() int {
	slots := atomic.LoadInt32(&c.maxBufferSize)/2 - atomic.LoadInt32(&c.persistValueNum)
	if slots < 0 {
		slots = 0
	}
	return int(slots)
}
