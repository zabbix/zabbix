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

package stats

import (
	"errors"
	"fmt"
	"strconv"
	"strings"
	"sync"
	"time"
	"unsafe"

	"golang.zabbix.com/agent2/pkg/pdh"
	"golang.zabbix.com/agent2/pkg/win32"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	modeParam = 2
	cpuParam  = 1
	noParam   = 0

	defaultIndex = 60
)

const (
	maxInactivityPeriod = time.Hour * 25
	maxInterval         = 60 * 15

	langDefault = 0
	langEnglish = 1
)

var impl Plugin = Plugin{
	counters:    make(map[perfCounterIndex]*perfCounter),
	countersErr: make(map[perfCounterIndex]*perfCounterErrorInfo),
}

type perfCounterIndex struct {
	path string
	lang int
}

type perfCounterAddInfo struct {
	index    perfCounterIndex
	interval int64
}

type perfCounterErrorInfo struct {
	lastAccess time.Time
	err        error
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
	cpus         []*cpuUnit
	collector    *pdhCollector
	mutex        sync.Mutex
	historyMutex sync.Mutex
	counters     map[perfCounterIndex]*perfCounter
	countersErr  map[perfCounterIndex]*perfCounterErrorInfo
	addCounters  []perfCounterAddInfo
	query        win32.PDH_HQUERY
	collectError error
	stop         chan bool
}

func init() {
	impl.collector = newPdhCollector(&impl)

	err := plugin.RegisterMetrics(
		&impl, pluginName,
		"system.cpu.discovery", "List of detected CPUs/CPU cores, used for low-level discovery.",
		"system.cpu.load", "CPU load.",
		"system.cpu.num", "Number of CPUs.",
		"system.cpu.util", "CPU utilization percentage.",
		"perf_counter", "Value of any Windows performance counter.",
		"perf_counter_en", "Value of any Windows performance counter in English.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

func numCPUOnline() int {
	return numCPU()
}

func numCPUConf() int {
	// unsupported on Windows
	return 0
}

func numCPU() (numCpu int) {
	size, err := win32.GetLogicalProcessorInformationEx(win32.RelationProcessorCore, nil)
	if err != nil {
		return
	}

	b := make([]byte, size)
	size, err = win32.GetLogicalProcessorInformationEx(win32.RelationProcessorCore, b)
	if err != nil {
		return
	}

	var sinfo *win32.SYSTEM_LOGICAL_PROCESSOR_INFORMATION_EX
	for i := uint32(0); i < size; i += sinfo.Size {
		sinfo = (*win32.SYSTEM_LOGICAL_PROCESSOR_INFORMATION_EX)(unsafe.Pointer(&b[i]))
		pinfo := (*win32.PROCESSOR_RELATIONSHIP)(unsafe.Pointer(&sinfo.Data[0]))
		groups := (*win32.RGGROUP_AFFINITY)(unsafe.Pointer(&pinfo.GroupMask[0]))[:pinfo.GroupCount:pinfo.GroupCount]
		for _, group := range groups {
			for mask := group.Mask; mask != 0; mask >>= 1 {
				numCpu += int(mask & 1)
			}
		}
	}
	return
}

func (p *Plugin) getCpuLoad(params []string) (result interface{}, err error) {
	split := 1

	period := historyIndex(defaultIndex)
	switch len(params) {
	case modeParam: // mode parameter
		if period = periodByMode(params[1]); period < 0 {
			return nil, errors.New("Invalid first parameter.")
		}

		fallthrough
	case cpuParam: // all, cpu number or per cpu
		switch params[0] {
		case "", "all":
		case "percpu":
			split = numCPUOnline()
		default:
			return nil, errors.New("Invalid second parameter.")
		}
	case noParam:
	default:
		return nil, zbxerr.ErrorTooManyParameters
	}

	return p.cpus[0].counterAverage(counterLoad, period, split), nil
}

func (p *Plugin) collectCpuData() (err error) {
	ok, err := p.collector.collect()
	if err != nil || !ok {
		return
	}

	for i, cpu := range p.cpus {
		slot := &cpu.history[cpu.tail]
		cpu.status = cpuStatusOnline
		if i == 0 {
			// gather cpu load into 'total' slot
			slot.load += p.collector.cpuLoad()
		}
		slot.util += p.collector.cpuUtil(i)

		if cpu.tail = cpu.tail.inc(maxHistory); cpu.tail == cpu.head {
			cpu.head = cpu.head.inc(maxHistory)
		}
		// write the current value into next slot so next time the new value
		// can be added to it resulting in incrementing counter
		nextSlot := &cpu.history[cpu.tail]
		*nextSlot = *slot
	}
	return
}

func (p *Plugin) Start() {
	numCpus := numCPU()
	numGroups := getNumaNodeCount()
	if numCpus == 0 || numGroups == 0 {
		p.Warningf("cannot calculate the number of CPUs per group, only total values will be available")
	}
	p.cpus = p.newCpus(numCpus)
	p.collector.open(numCpus, numGroups)

	p.stop = make(chan bool)

	go func() {
		var lastCheck time.Time
		var err error

		for {
			select {
			case <-p.stop:
				return
			default:
				time.Sleep(lastCheck.Add(1 * time.Second).Sub(time.Now()))

				err = p.collectCounterData()
				if err != nil {
					p.Warningf("failed to get performance counters data: '%s'", err)
				}

				err = p.collectCpuData()
				if err != nil {
					p.Warningf("failed to get CPU performance data: '%s'", err)
				}

				lastCheck = time.Now()
			}
		}
	}()
}

func (p *Plugin) Stop() {
	p.collector.close()
	p.cpus = nil

	p.stop <- true
}

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if strings.HasPrefix(key, "system.") {
		result, err = p.export_cpu(key, params, ctx)
	} else {
		result, err = p.export_perf(key, params, ctx)
	}

	return
}

func (p *Plugin) export_cpu(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if p.cpus == nil || p.cpus[0].head == p.cpus[0].tail {
		// no data gathered yet
		return
	}
	switch key {
	case "system.cpu.discovery":
		return p.getCpuDiscovery(params)
	case "system.cpu.load":
		return p.getCpuLoad(params)
	case "system.cpu.num":
		if len(params) > 0 && params[0] == "max" {
			return nil, errors.New("Invalid first parameter.")
		}
		return p.getCpuNum(params)
	case "system.cpu.util":
		return p.getCpuUtil(params)
	default:
		return nil, plugin.UnsupportedMetricError
	}
}

// Export -
func (p *Plugin) export_perf(key string, params []string, ctx plugin.ContextProvider) (any, error) {
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

	index := perfCounterIndex{path, lang}
	p.historyMutex.Lock()
	defer p.historyMutex.Unlock()
	counter, ok := p.counters[index]
	if !ok {
		counterErr, ok := p.countersErr[index]
		if ok {
			counterErr.lastAccess = time.Now()

			return nil, counterErr.err
		}

		for _, addCnt := range p.addCounters {
			if addCnt.index == index {
				return nil, nil
			}
		}

		p.addCounters = append(p.addCounters, perfCounterAddInfo{index, interval})

		return nil, nil
	}

	if p.collectError != nil {
		return nil, p.collectError
	}

	return counter.getHistory(int(interval))
}

func (p *Plugin) collectCounterData() error {
	p.mutex.Lock()
	defer p.mutex.Unlock()

	if len(p.counters) == 0 && len(p.addCounters) == 0 {
		return nil
	}

	var err error
	if p.query == 0 {
		p.query, err = win32.PdhOpenQuery(nil, 0)
		if err != nil {
			return zbxerr.New("cannot open query").Wrap(err)
		}
	}

	expireTime := time.Now().Add(-maxInactivityPeriod)

	p.historyMutex.Lock()
	addCountersLocal := p.addCounters
	p.addCounters = nil
	p.collectError = nil

	for index, c := range p.countersErr {
		if c.lastAccess.Before(expireTime) {
			delete(p.countersErr, index)
		}
	}
	p.historyMutex.Unlock()

	for i := len(addCountersLocal) - 1; i >= 0; i-- {
		addInfo := addCountersLocal[i]
		err = p.addCounter(addInfo.index, addInfo.interval)
		if err != nil {
			p.historyMutex.Lock()

			p.countersErr[addInfo.index] = &perfCounterErrorInfo{
				lastAccess: time.Now(),
				err: errs.Wrap(err, fmt.Sprintf("failed to get counter for path %q and lang %d", addInfo.index.path,
					addInfo.index.lang)),
			}

			p.historyMutex.Unlock()
		}
	}

	if len(p.counters) == 0 {
		return nil
	}

	err = p.setCounterData()
	if err != nil {
		p.Debugf("reset counter query: '%s'", err)

		p.historyMutex.Lock()
		p.collectError = err
		p.historyMutex.Unlock()

		err2 := win32.PdhCloseQuery(p.query)
		if err2 != nil {
			p.Warningf("error while closing query '%s'", err2)
		}

		p.query = 0

		return err
	}

	return nil
}

func (p *Plugin) setCounterData() error {
	errCollect := win32.PdhCollectQueryData(p.query)
	if errCollect != nil {
		errCollect = fmt.Errorf("cannot collect value %s", errCollect)
	}

	expireTime := time.Now().Add(-maxInactivityPeriod)

	for index, c := range p.counters {
		if c.lastAccess.Before(expireTime) || errCollect != nil {
			err2 := win32.PdhRemoveCounter(c.handle)
			if err2 != nil {
				p.Warningf("error while removing counter '%s': %s", index.path, err2)
			}

			p.historyMutex.Lock()
			delete(p.counters, index)
			p.historyMutex.Unlock()

			continue
		}

		c.err = nil

		histValue, err := win32.PdhGetFormattedCounterValueDouble(c.handle, 1)
		p.historyMutex.Lock()
		if err != nil {
			zbxErr := zbxerr.New(
				fmt.Sprintf("failed to retrieve pdh counter value double for index %s", index.path),
			).Wrap(err)
			if !errors.Is(err, win32.NegDenomErr) {
				c.err = zbxErr
			}

			p.Debugf("%s", zbxErr)
		} else {
			c.history[c.tail] = histValue
		}

		if c.tail = c.tail.inc(c.interval); c.tail == c.head {
			c.head = c.head.inc(c.interval)
		}
		p.historyMutex.Unlock()
	}

	return errCollect
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
	start := c.tail.sub(historyIndex(interval), c.interval)
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
