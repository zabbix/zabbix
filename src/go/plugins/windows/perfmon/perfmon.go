//go:build windows
// +build windows

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

package perfmon

import (
	"errors"
	"strconv"
	"sync"
	"time"

	"git.zabbix.com/ap/plugin-support/plugin"
	"zabbix.com/pkg/pdh"
	"zabbix.com/pkg/win32"
)

const (
	maxInactivityPeriod = time.Hour * 25
	maxInterval         = 60 * 15

	langDefault = 0
	langEnglish = 1
)

type perfCounterIndex struct {
	path string
	lang int
}

type perfCounter struct {
	lastAccess time.Time
	interval   int
	handle     win32.PDH_HCOUNTER
	history    []*float64
	head, tail historyIndex
	err        error
}

// Plugin -
type Plugin struct {
	plugin.Base
	mutex        sync.Mutex
	counters     map[perfCounterIndex]*perfCounter
	query        win32.PDH_HQUERY
	collectError error
}

var impl Plugin = Plugin{
	counters: make(map[perfCounterIndex]*perfCounter),
}

type historyIndex int

func (h historyIndex) inc(interval int) historyIndex {
	h++
	if int(h) == interval {
		h = 0
	}
	return h
}

func (h historyIndex) dec(interval int) historyIndex {
	h--
	if int(h) < 0 {
		h = historyIndex(interval - 1)
	}
	return h
}

func (h historyIndex) sub(value int, interval int) historyIndex {
	h -= historyIndex(value)
	for int(h) < 0 {
		h += historyIndex(interval)
	}
	return h
}

func (p *Plugin) Collect() (err error) {
	p.mutex.Lock()
	defer p.mutex.Unlock()

	if len(p.counters) == 0 {
		return
	}

	if p.collectError = win32.PdhCollectQueryData(p.query); p.collectError != nil {
		return p.collectError
	}

	expireTime := time.Now().Add(-maxInactivityPeriod)
	for index, c := range p.counters {
		if c.lastAccess.Before(expireTime) {
			if cerr := win32.PdhRemoveCounter(c.handle); cerr != nil {
				p.Debugf("error while removing counter '%s': %s", index.path, cerr)
			}
			delete(p.counters, index)
			continue
		}
		c.history[c.tail], c.err = win32.PdhGetFormattedCounterValueDouble(c.handle)
		if c.tail = c.tail.inc(c.interval); c.tail == c.head {
			c.head = c.head.inc(c.interval)
		}
	}
	return
}

func (p *Plugin) Period() int {
	return 1
}

// addCounter adds new performance counter to query. The plugin mutex must be locked.
func (p *Plugin) addCounter(index perfCounterIndex, interval int64) (err error) {
	var handle win32.PDH_HCOUNTER
	if index.lang == langEnglish {
		handle, err = win32.PdhAddEnglishCounter(p.query, index.path, 0)
	} else {
		handle, err = win32.PdhAddCounter(p.query, index.path, 0)
	}
	if err != nil {
		return
	}
	// extend the interval buffer by 1 to reserve space so tail/head doesn't overlap
	// when the buffer is full
	interval++

	p.counters[index] = &perfCounter{
		lastAccess: time.Now(),
		history:    make([]*float64, interval),
		interval:   int(interval),
		handle:     handle,
	}
	return
}

func (c *perfCounter) getHistory(interval int) (value interface{}, err error) {
	c.lastAccess = time.Now()
	if c.err != nil {
		return nil, c.err
	}

	// extend history buffer if necessary
	if c.interval < interval+1 {
		h := make([]*float64, interval+1)
		copy(h, c.history)
		c.history = h
		c.interval = interval + 1
	}

	totalnum := int(c.tail - c.head)
	if totalnum < 0 {
		totalnum += c.interval
	}
	if totalnum == 0 {
		// not enough samples collected
		return
	}
	if interval == 1 {
		if pvalue := c.history[c.tail.dec(c.interval)]; pvalue != nil {
			return *pvalue, nil
		}
		return nil, nil
	}

	if totalnum < interval {
		interval = totalnum
	}
	start := c.tail.sub(interval, c.interval)
	var total, num float64
	for index := start; index != c.tail; index = index.inc(c.interval) {
		if pvalue := c.history[index]; pvalue != nil {
			total += *c.history[index]
			num++
		}
	}
	if num != 0 {
		return total / num, nil
	}
	return nil, nil
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	var lang int
	switch key {
	case "perf_counter":
		lang = langDefault
	case "perf_counter_en":
		lang = langEnglish
	default:
		return nil, errors.New("Unsupported metric.")
	}

	if ctx == nil {
		return nil, errors.New("This item is available only in daemon mode.")
	}

	if len(params) > 2 {
		return nil, errors.New("Too many parameters.")
	}
	if len(params) == 0 || params[0] == "" {
		return nil, errors.New("Invalid first parameter.")
	}

	var interval int64
	if len(params) == 1 || params[1] == "" {
		interval = 1
	} else {
		if interval, err = strconv.ParseInt(params[1], 10, 32); err != nil {
			return nil, errors.New("Invalid second parameter.")
		}
		if interval < 1 || interval > maxInterval {
			return nil, errors.New("Interval out of range.")
		}
	}

	if path, tmperr := pdh.ConvertPath(params[0]); tmperr != nil {
		p.Debugf("cannot convert performance counter path: %s", tmperr)
		return nil, errors.New("Invalid performance counter path.")
	} else {
		p.mutex.Lock()
		defer p.mutex.Unlock()

		if p.collectError != nil {
			return nil, p.collectError
		}

		if p.query == 0 {
			if p.query, err = win32.PdhOpenQuery(nil, 0); err != nil {
				return
			}
		}

		index := perfCounterIndex{path, lang}
		if counter, ok := p.counters[index]; ok {
			return counter.getHistory(int(interval))
		} else {
			return nil, p.addCounter(index, interval)
		}
	}
}

func (p *Plugin) Start() {
}

func (p *Plugin) Stop() {
	p.counters = make(map[perfCounterIndex]*perfCounter)

	_ = win32.PdhCloseQuery(p.query)
	p.query = 0
}

func init() {
	plugin.RegisterMetrics(&impl, "WindowsPerfMon",
		"perf_counter", "Value of any Windows performance counter.",
		"perf_counter_en", "Value of any Windows performance counter in English.",
	)
}
