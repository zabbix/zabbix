//go:build windows
// +build windows

/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
	"fmt"
	"strconv"
	"sync"
	"time"

	"git.zabbix.com/ap/plugin-support/errs"
	"git.zabbix.com/ap/plugin-support/plugin"
	"git.zabbix.com/ap/plugin-support/zbxerr"
	"zabbix.com/pkg/pdh"
	"zabbix.com/pkg/win32"
)

const (
	maxInactivityPeriod = time.Hour * 25
	maxInterval         = 60 * 15

	langDefault = 0
	langEnglish = 1
)

var impl Plugin = Plugin{
	counters: make(map[perfCounterIndex]*perfCounter),
}

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

type historyIndex int

func init() {
	err := plugin.RegisterMetrics(
		&impl, "WindowsPerfMon",
		"perf_counter", "Value of any Windows performance counter.",
		"perf_counter_en", "Value of any Windows performance counter in English.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (any, error) {
	var lang int
	switch key {
	case "perf_counter":
		lang = langDefault
	case "perf_counter_en":
		lang = langEnglish
	default:
		return nil, zbxerr.New(fmt.Sprintf("metric key %q not found", key)).Wrap(zbxerr.ErrorUnsupportedMetric)
	}

	if ctx == nil {
		return nil, zbxerr.New("this item is available only in daemon mode")
	}

	if len(params) > 2 {
		return nil, zbxerr.ErrorTooManyParameters
	}
	if len(params) == 0 || params[0] == "" {
		return nil, zbxerr.New("invalid first parameter")
	}

	var interval int64 = 1
	var err error

	if len(params) == 2 && params[1] != "" {
		if interval, err = strconv.ParseInt(params[1], 10, 32); err != nil {
			return nil, zbxerr.New("invalid second parameter").Wrap(err)
		}

		if interval < 1 || interval > maxInterval {
			return nil, zbxerr.New(fmt.Sprintf("interval %d out of range [%d, %d]", interval, 1, maxInterval))
		}
	}

	path, err := pdh.ConvertPath(params[0])
	if err != nil {
		p.Debugf("cannot convert performance counter path: %s", err)
		return nil, zbxerr.New("invalid performance counter path")
	}

	p.mutex.Lock()
	defer p.mutex.Unlock()

	if p.query == 0 {
		p.query, err = win32.PdhOpenQuery(nil, 0)
		if err != nil {
			return nil, zbxerr.New("cannot open query").Wrap(err)
		}
	}

	index := perfCounterIndex{path, lang}
	counter, ok := p.counters[index]
	if !ok {
		err = p.addCounter(index, interval)
		if err != nil {
			p.collectError = zbxerr.New(
				fmt.Sprintf("failed to get counter for path %q and lang %d", path, lang),
			).Wrap(err)
		}

		return nil, p.collectError
	}

	if p.collectError != nil {
		return nil, p.collectError
	}

	return counter.getHistory(int(interval))
}

func (p *Plugin) Collect() error {
	p.mutex.Lock()
	defer p.mutex.Unlock()

	if len(p.counters) == 0 {
		return nil
	}

	p.setCounterData()

	if p.collectError != nil {
		p.Debugf("reset counter query: '%s'", p.collectError)

		err := win32.PdhCloseQuery(p.query)
		if err != nil {
			p.Warningf("error while closing query '%s'", err)
		}

		p.query = 0

		return p.collectError
	}

	return nil
}

func (p *Plugin) Period() int {
	return 1
}

func (p *Plugin) Start() {
}

func (p *Plugin) Stop() {
}

func (p *Plugin) setCounterData() {
	p.collectError = nil

	err := win32.PdhCollectQueryData(p.query)
	if err != nil {
		p.collectError = fmt.Errorf("cannot collect value %s", err)
	}

	expireTime := time.Now().Add(-maxInactivityPeriod)

	for index, c := range p.counters {
		if c.lastAccess.Before(expireTime) || p.collectError != nil {
			err = win32.PdhRemoveCounter(c.handle)
			if err != nil {
				p.Warningf("error while removing counter '%s': %s", index.path, err)
			}

			delete(p.counters, index)

			continue
		}

		c.err = nil

		c.history[c.tail], err = win32.PdhGetFormattedCounterValueDouble(c.handle, 1)
		if err != nil {
			zbxErr := zbxerr.New(
				fmt.Sprintf("failed to retrieve pdh counter value double for index %s", index.path),
			).Wrap(err)
			if !errors.Is(err, win32.NegDenomErr) {
				c.err = zbxErr
			}

			p.Debugf("%s", zbxErr)
		}

		if c.tail = c.tail.inc(c.interval); c.tail == c.head {
			c.head = c.head.inc(c.interval)
		}
	}
}

// addCounter adds new performance counter to query. The plugin mutex must be locked.
func (p *Plugin) addCounter(index perfCounterIndex, interval int64) error {
	handle, err := p.getCounters(index)
	if err != nil {
		return err
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

	return nil
}

func (p *Plugin) getCounters(index perfCounterIndex) (win32.PDH_HCOUNTER, error) {
	var counter win32.PDH_HCOUNTER
	var err error

	if index.lang == langEnglish {
		counter, err = win32.PdhAddEnglishCounter(p.query, index.path, 0)
		if err != nil {
			return 0, zbxerr.New("cannot add english counter").Wrap(err)
		}

		return counter, nil
	}

	counter, err = win32.PdhAddCounter(p.query, index.path, 0)
	if err != nil {
		return 0, zbxerr.New("cannot add counter").Wrap(err)
	}

	return counter, nil
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

func (h historyIndex) sub(value, interval int) historyIndex {
	h -= historyIndex(value)
	for int(h) < 0 {
		h += historyIndex(interval)
	}

	return h
}
