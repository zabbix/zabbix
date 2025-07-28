/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/
package resultcache

import (
	"encoding/json"
	"errors"
	"reflect"
	"sync/atomic"
	"time"

	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/internal/monitor"
	"golang.zabbix.com/agent2/pkg/itemutil"
	"golang.zabbix.com/agent2/pkg/version"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin"
)

type MemoryCache struct {
	*cacheData
	results         []*AgentData
	cresults        []*AgentCommands
	maxBufferSize   int32
	totalValueNum   int32
	persistValueNum int32
	historyUpload   bool
}

func (c *MemoryCache) upload(u Uploader) (err error) {
	resultsLen := len(c.results) + len(c.cresults)
	if resultsLen == 0 {
		return
	}

	c.Debugf("upload history data, %d/%d value(s) commands %d/%d value(s)", len(c.results), cap(c.results),
		len(c.cresults), cap(c.cresults))

	request := AgentDataRequest{
		Request:  "agent data",
		Data:     c.results,
		Commands: c.cresults,
		Session:  u.Session(),
		Host:     u.Hostname(),
		Version:  version.Long(),
		Variant:  agent.Variant,
	}

	var data []byte

	if data, err = json.Marshal(&request); err != nil {
		c.Errf("cannot convert cached history to json: %s", err.Error())
		return
	}

	timeout := resultsLen * c.timeout
	if timeout > 60 {
		timeout = 60
	}

	upload, errs := u.Write(data, time.Duration(timeout)*time.Second)
	c.EnableUpload(upload)

	if errs != nil {
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
	if len(c.results) != 0 {
		c.results[0] = nil
		for i := 1; i < len(c.results); i *= 2 {
			copy(c.results[i:], c.results[:i])
		}
		c.results = c.results[:0]
	}

	if len(c.cresults) != 0 {
		c.cresults[0] = nil
		for i := 1; i < len(c.cresults); i *= 2 {
			copy(c.cresults[i:], c.cresults[:i])
		}
		c.cresults = c.cresults[:0]
	}

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

func (c *MemoryCache) addCommandResult(result *AgentCommands) {
	c.cresults = append(c.cresults, result)
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

func (c *MemoryCache) writeCommand(cr *CommandResult) {
	var value *string
	var err *string

	log.Debugf("cache command(%d) result:%s error:%s", cr.ID, cr.Result, cr.Error)

	c.lastCommandID++

	if cr.Result != "" {
		value = &cr.Result
	}

	if cr.Error != nil {
		err_msg := cr.Error.Error()
		err = &err_msg
	}

	cmd := &AgentCommands{
		Id:    cr.ID,
		Value: value,
		Error: err}

	c.addCommandResult(cmd)
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
		case *CommandResult:
			c.writeCommand(v)
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
