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

package cpucollector

import (
	"encoding/json"
	"errors"
	"strconv"

	"zabbix.com/pkg/log"
	"zabbix.com/pkg/plugin"
)

// Plugin -
type Plugin struct {
	plugin.Base
	cpus []*cpuUnit
}

var impl Plugin

const (
	maxHistory = 60*15 + 1
)

const (
	cpuStatusOffline = 1 << iota
	cpuStatusOnline
)
const (
	stateUser = iota
	stateNice
	stateSystem
	stateIdle
	stateIowait
	stateIrq
	stateSoftirq
	stateSteal
	stateGcpu
	stateGnice
)

var cpuStatuses [3]string = [3]string{"", "offline", "online"}

type historyIndex int

func (h historyIndex) inc() historyIndex {
	h++
	if h == maxHistory {
		h = 0
	}
	return h
}

func (h historyIndex) dec() historyIndex {
	h--
	if h < 0 {
		h = maxHistory - 1
	}
	return h
}

func (h historyIndex) sub(value historyIndex) historyIndex {
	h -= value
	for h < 0 {
		h += maxHistory
	}
	return h
}

type cpuStats struct {
	counters [10]uint64
}

type cpuUnit struct {
	index      int
	head, tail historyIndex
	history    [maxHistory]cpuStats
	status     int
}

type cpuDiscovery struct {
	Number int    `json:"{#CPU.NUMBER}"`
	Status string `json:"{#CPU.STATUS}"`
}

func (p *Plugin) Collect() (err error) {
	return p.collect()
}

func (p *Plugin) Period() int {
	return 1
}

func (p *Plugin) getCpuDiscovery(params []string) (result interface{}, err error) {
	if len(params) > 0 {
		return nil, errors.New("Too many parameters.")
	}
	cpus := make([]*cpuDiscovery, 0, len(p.cpus))
	// 0 index is for the overall stats, skip for discovery
	for i := 1; i < len(p.cpus); i++ {
		cpu := p.cpus[i]
		cpus = append(cpus, &cpuDiscovery{Number: cpu.index, Status: cpuStatuses[cpu.status]})
	}
	var b []byte
	if b, err = json.Marshal(&cpus); err != nil {
		return
	}
	return string(b), nil
}

func (p *Plugin) getCpuNum(params []string) (result interface{}, err error) {
	mask := cpuStatusOnline
	switch len(params) {
	case 1:
		switch params[0] {
		case "", "online":
			// default value, already initialized
		case "max":
			mask = cpuStatusOnline | cpuStatusOffline
		default:
			return nil, errors.New("Invalid first parameter.")
		}
	case 0:
	default:
		return nil, errors.New("Too many parameters.")
	}

	var num int
	for _, cpu := range p.cpus {
		if cpu.status&mask != 0 {
			num++
		}
	}
	return num, nil
}

func (p *Plugin) getCpuUtil(params []string) (result interface{}, err error) {
	index := 0
	state := stateUser
	statRange := historyIndex(60)

	switch len(params) {
	case 3: // mode parameter
		switch params[2] {
		case "", "avg1":
			statRange = 60
		case "avg5":
			statRange = 60 * 5
		case "avg15":
			statRange = 60 * 15
		default:
			return nil, errors.New("Invalid third parameter.")
		}

		fallthrough
	case 2: // type parameter
		if state, err = p.getStateIndex(params[1]); err != nil {
			return nil, errors.New("Invalid second parameter.")
		}
		fallthrough
	case 1: // cpu number or all;
		if params[0] != "" && params[0] != "all" {
			if i, err := strconv.ParseInt(params[0], 10, 32); err != nil {
				return nil, errors.New("Invalid first parameter.")
			} else {
				index = int(i) + 1
			}
		}
	case 0:
	default:
		return nil, errors.New("Too many parameters.")
	}

	if index < 0 || index >= len(p.cpus) {
		return nil, errors.New("Invalid first parameter.")
	}
	cpu := p.cpus[index]
	if cpu.status == cpuStatusOffline {
		return nil, errors.New("CPU is offline.")
	}
	if cpu.head == cpu.tail {
		log.Debugf("no collected data for CPU %d", index-1)
		return nil, nil
	}

	var tail, head *cpuStats
	totalnum := cpu.tail - cpu.head
	if totalnum < 0 {
		totalnum += maxHistory
	}
	if totalnum < 2 {
		// need at least two samples to calculate utilization
		return
	}
	if totalnum < statRange {
		statRange = totalnum
	}
	tail = &cpu.history[cpu.tail.dec()]
	if totalnum > 1 {
		head = &cpu.history[cpu.tail.sub(statRange)]
	} else {
		head = &cpuStats{}
	}

	var counter, total uint64
	for i := 0; i < len(tail.counters); i++ {
		if tail.counters[i] > head.counters[i] {
			total += tail.counters[i] - head.counters[i]
		}
	}
	if total == 0 {
		return 0, nil
	}

	if tail.counters[state] > head.counters[state] {
		counter = tail.counters[state] - head.counters[state]
	}

	return float64(counter) * 100 / float64(total), nil
}

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if p.cpus == nil || p.cpus[0].head == p.cpus[0].tail {
		// no data gathered yet
		return
	}
	switch key {
	case "system.cpu.discovery":
		return p.getCpuDiscovery(params)
	case "system.cpu.num":
		return p.getCpuNum(params)
	case "system.cpu.util":
		return p.getCpuUtil(params)
	default:
		return nil, plugin.UnsupportedMetricError
	}
}

func (p *Plugin) Start() {
	impl.cpus = make([]*cpuUnit, impl.numCPU()+1)
	for i := 0; i < len(impl.cpus); i++ {
		impl.cpus[i] = &cpuUnit{
			index:  i - 1,
			status: cpuStatusOffline,
		}
	}
}

func (p *Plugin) Stop() {
	impl.cpus = nil
}

func init() {
	plugin.RegisterMetrics(&impl, "CpuCollector",
		"system.cpu.discovery", "List of detected CPUs/CPU cores, used for low-level discovery.",
		"system.cpu.num", "Number of CPUs.",
		"system.cpu.util", "CPU utilisation percentage.")
}
