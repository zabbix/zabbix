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
	"encoding/json"
	"errors"
	"strconv"

	"golang.zabbix.com/sdk/zbxerr"
)

const pluginName = "Cpu"

var impl Plugin

const (
	maxHistory = 60*15 + 2
)

const (
	cpuStatusOffline = 1 << iota
	cpuStatusOnline
)

var cpuStatuses [3]string = [3]string{"", "offline", "online"}

type cpuCounter int
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

type cpuUnit struct {
	index      int
	head, tail historyIndex
	history    [maxHistory]cpuCounters
	status     int
}

type cpuDiscovery struct {
	Number int    `json:"{#CPU.NUMBER}"`
	Status string `json:"{#CPU.STATUS}"`
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
	switch len(params) {
	case 1:
		switch params[0] {
		case "", "online":
			return numCPUOnline(), nil
		case "max":
			return numCPUConf(), nil
		default:
			return nil, errors.New("Invalid first parameter.")
		}
	case 0:
		return numCPUOnline(), nil
	default:
		return nil, zbxerr.ErrorTooManyParameters
	}
}

func periodByMode(mode string) (period historyIndex) {
	switch mode {
	case "", "avg1":
		return 60
	case "avg5":
		return 60 * 5
	case "avg15":
		return 60 * 15
	default:
		return -1
	}
}

func indexByCpu(cpu string) (index int) {
	if cpu == "" || cpu == "all" {
		return 0
	}
	if i, err := strconv.ParseInt(cpu, 10, 32); err != nil {
		return -1
	} else {
		return int(i) + 1
	}
}

func (p *Plugin) getCpuUtil(params []string) (result interface{}, err error) {
	var index int
	var counter cpuCounter
	period := historyIndex(60)
	switch len(params) {
	case 3: // mode parameter
		if period = periodByMode(params[2]); period < 0 {
			return nil, errors.New("Invalid third parameter.")
		}
		fallthrough
	case 2: // type parameter
		if counter = counterByType(params[1]); counter == counterUnknown {
			return nil, errors.New("Invalid second parameter.")
		}
		fallthrough
	case 1: // cpu number or all;
		if index = indexByCpu(params[0]); index < 0 || index >= len(p.cpus) {
			return nil, errors.New("Invalid first parameter.")
		}
	case 0:
	default:
		return nil, errors.New("Too many parameters.")
	}

	cpu := p.cpus[index]
	if cpu.status == cpuStatusOffline {
		return nil, errors.New("CPU is offline.")
	}

	return p.getCounterAverage(cpu, counter, period), nil
}

func (p *Plugin) newCpus(num int) (cpus []*cpuUnit) {
	cpus = make([]*cpuUnit, num+1)
	for i := 0; i < len(cpus); i++ {
		cpus[i] = &cpuUnit{
			index:  i - 1,
			status: cpuStatusOffline,
		}
	}
	return
}
