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

package cpu

import (
	"errors"
	"sync"
	"time"
	"unsafe"

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

// Plugin -
type Plugin struct {
	plugin.Base
	cpus      []*cpuUnit
	collector *pdhCollector
	cpusMu    sync.Mutex
	stop      chan struct{}
}

func init() {
	impl.collector = newPdhCollector(&impl)

	err := plugin.RegisterMetrics(
		&impl, pluginName,
		"system.cpu.discovery", "List of detected CPUs/CPU cores, used for low-level discovery.",
		"system.cpu.load", "CPU load.",
		"system.cpu.num", "Number of CPUs.",
		"system.cpu.util", "CPU utilization percentage.",
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

func (p *Plugin) getCPULoad(params []string) (result any, err error) {
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

	p.cpusMu.Lock()
	defer p.cpusMu.Unlock()

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

		p.cpusMu.Lock()
		if cpu.tail = cpu.tail.inc(); cpu.tail == cpu.head {
			cpu.head = cpu.head.inc()
		}
		// write the current value into next slot so next time the new value
		// can be added to it resulting in incrementing counter
		nextSlot := &cpu.history[cpu.tail]
		*nextSlot = *slot
		p.cpusMu.Unlock()
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

	p.stop = make(chan struct{})

	go func() {
		t := time.NewTicker(1 * time.Second)
		defer t.Stop()
		for {
			select {
			case <-p.stop:
				return
			case <-t.C:
				p.Debugf("starting to collect CPU performance data")

				err := p.collectCpuData()
				if err != nil {
					p.Warningf("failed to get CPU performance data: '%s'", err)
					continue
				}

				p.Debugf("collected CPU performance data")
			}
		}
	}()
}

func (p *Plugin) Stop() {
	p.collector.close()
	p.cpus = nil

	p.stop <- struct{}{}
}

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if p.cpus == nil || p.cpus[0].head == p.cpus[0].tail {
		// no data gathered yet
		return
	}
	switch key {
	case "system.cpu.discovery":
		return p.getCpuDiscovery(params)
	case "system.cpu.load":
		return p.getCPULoad(params)
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

func (p *Plugin) getCounterAverage(cpu *cpuUnit, counter cpuCounter, period historyIndex) any {
	p.cpusMu.Lock()
	defer p.cpusMu.Unlock()
	return cpu.counterAverage(counter, period, 1)
}
