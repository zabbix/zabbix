/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

// package resultcache provides result caching component.
//
// ResultCache runs in separate goroutine, caches results and flushes data to the
// specified output interface in json format when requested or cache is full.
// The cache limits are specified by configuration file (BufferSize). If cache
// limits are reached the following logic is applied to new results:
// * non persistent results replaces either oldest result of the same item, or
//   oldest non persistent result if item was not yet cached.
// * persistent results replaces oldest non persistent result if the total number
//   of persistent results is less than half maximum cache size. Otherwise the result
//   is appended, extending cache beyond configured limit.
//
// Because of asynchronous nature of the communications it's not possible for
// result cache to return error if it cannot accept new persistent result. So
// instead before writing result to the cache the caller (plugin) must check
// the result cache state with PersistSlotsAvailable() function. This still
// can lead to more results written than cache limits allow. However it's not a
// big problem because cache buffer is not static and will be extended as required.
// The cache limit (BufferSize) is treated more like recommendation than hard limit.
//
package resultcache

import (
	"crypto/md5"
	"encoding/binary"
	"encoding/hex"
	"encoding/json"
	"sync/atomic"
	"time"

	"zabbix.com/internal/agent"
	"zabbix.com/internal/monitor"
	"zabbix.com/pkg/itemutil"
	"zabbix.com/pkg/log"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/version"
)

const (
	UploadRetryInterval = time.Second
)

type ResultCache struct {
	input           chan interface{}
	output          Uploader
	results         []*AgentData
	token           string
	lastDataID      uint64
	clientID        uint64
	lastError       error
	maxBufferSize   int32
	totalValueNum   int32
	persistValueNum int32
	retry           *time.Timer
	timeout         int
}

type AgentData struct {
	Id             uint64  `json:"id"`
	Itemid         uint64  `json:"itemid"`
	LastLogsize    *uint64 `json:"lastlogsize,omitempty"`
	Mtime          *int    `json:"mtime,omitempty"`
	State          *int    `json:"state,omitempty"`
	Value          *string `json:"value,omitempty"`
	EventSource    *string `json:"source,omitempty"`
	EventID        *int    `json:"eventid,omitempty"`
	EventSeverity  *int    `json:"severity,omitempty"`
	EventTimestamp *int    `json:"timestamp,omitempty"`
	Clock          int     `json:"clock,omitempty"`
	Ns             int     `json:"ns,omitempty"`
	persistent     bool
}

type AgentDataRequest struct {
	Request string       `json:"request"`
	Data    []*AgentData `json:"data"`
	Session string       `json:"session"`
	Host    string       `json:"host"`
	Version string       `json:"version"`
}

type Uploader interface {
	Write(data []byte, timeout time.Duration) (err error)
	Addr() (s string)
	CanRetry() (enabled bool)
}

func (c *ResultCache) upload(u Uploader) (err error) {
	if len(c.results) == 0 {
		return
	}

	log.Debugf("[%d] upload history data, %d/%d value(s)", c.clientID, len(c.results), cap(c.results))

	request := AgentDataRequest{
		Request: "agent data",
		Data:    c.results,
		Session: c.token,
		Host:    agent.Options.Hostname,
		Version: version.Short(),
	}

	var data []byte

	if data, err = json.Marshal(&request); err != nil {
		log.Errf("[%d] cannot convert cached history to json: %s", c.clientID, err.Error())
		return
	}

	timeout := len(c.results) * c.timeout
	if timeout > 60 {
		timeout = 60
	}
	if err = u.Write(data, time.Duration(timeout)*time.Second); err != nil {
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

func (c *ResultCache) flushOutput(u Uploader) {
	if c.retry != nil {
		c.retry.Stop()
		c.retry = nil
	}

	if c.upload(u) != nil && u.CanRetry() {
		c.retry = time.AfterFunc(UploadRetryInterval, func() { c.FlushOutput(u) })
	}
}

// addResult appends received result at the end of results slice
func (c *ResultCache) addResult(result *AgentData) {
	full := c.persistValueNum >= c.maxBufferSize/2 || c.totalValueNum >= c.maxBufferSize
	c.results = append(c.results, result)
	c.totalValueNum++
	if result.persistent {
		c.persistValueNum++
	}

	if c.persistValueNum >= c.maxBufferSize/2 || c.totalValueNum >= c.maxBufferSize {
		if !full && c.output != nil {
			c.flushOutput(c.output)
		}
	}
}

// insertResult attempts to insert the received result into results slice by replacing existing value.
// If no appropriate target was found it calls addResult to append value.
func (c *ResultCache) insertResult(result *AgentData) {
	index := -1
	if !result.persistent {
		for i, r := range c.results {
			if r.Itemid == result.Itemid {
				log.Debugf("[%d] cache is full, replacing oldest value for itemid:%d", c.clientID, r.Itemid)
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
				log.Debugf("[%d] cache is full, removing oldest value for itemid:%d", c.clientID, r.Itemid)
				index = i
				break
			}
		}
	}
	if index == -1 {
		log.Warningf("[%d] cache is full and cannot cannot find a value to replace, adding new instead", c.clientID)
		c.addResult(result)
		return
	}

	copy(c.results[index:], c.results[index+1:])
	c.results[len(c.results)-1] = result
}

func (c *ResultCache) write(r *plugin.Result) {
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

func (c *ResultCache) run() {
	defer log.PanicHook()
	log.Debugf("[%d] starting result cache", c.clientID)

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
	log.Debugf("[%d] result cache has been stopped", c.clientID)
	monitor.Unregister()
}

func newToken() string {
	h := md5.New()
	_ = binary.Write(h, binary.LittleEndian, time.Now().UnixNano())
	return hex.EncodeToString(h.Sum(nil))
}

func (c *ResultCache) updateOptions(options *agent.AgentOptions) {
	c.maxBufferSize = int32(options.BufferSize)
	c.timeout = options.Timeout
}

func (c *ResultCache) init() {
	c.updateOptions(&agent.Options)
	c.input = make(chan interface{}, 100)
	c.results = make([]*AgentData, 0, c.maxBufferSize)
}

func (c *ResultCache) Start() {
	monitor.Register()
	go c.run()
}

func (c *ResultCache) Stop() {
	c.input <- nil
}

func NewActive(clientid uint64, output Uploader) *ResultCache {
	cache := &ResultCache{clientID: clientid, output: output, token: newToken()}
	cache.init()
	return cache
}

func NewPassive(clientid uint64) *ResultCache {
	cache := &ResultCache{clientID: clientid, token: newToken()}
	cache.init()
	return cache
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

func (c *ResultCache) UpdateOptions(options *agent.AgentOptions) {
	c.input <- options
}

func (c *ResultCache) SlotsAvailable() int {
	slots := atomic.LoadInt32(&c.maxBufferSize) - atomic.LoadInt32(&c.totalValueNum)
	if slots < 0 {
		slots = 0
	}
	return int(slots)
}

func (c *ResultCache) PersistSlotsAvailable() int {
	slots := atomic.LoadInt32(&c.maxBufferSize)/2 - atomic.LoadInt32(&c.persistValueNum)
	if slots < 0 {
		slots = 0
	}
	return int(slots)
}
