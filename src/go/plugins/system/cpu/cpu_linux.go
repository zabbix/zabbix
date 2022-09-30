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

package cpu

/*
#include <unistd.h>
*/
import "C"

import (
	"bufio"
	"bytes"
	"os"
	"strconv"
	"strings"

	"git.zabbix.com/ap/plugin-support/plugin"
	"git.zabbix.com/ap/plugin-support/log"
)

// Plugin -
type Plugin struct {
	plugin.Base
	cpus []*cpuUnit
}

const (
	procStatLocation = "/proc/stat"
)

func (p *Plugin) getCpuLoad(params []string) (result interface{}, err error) {
	return nil, plugin.UnsupportedMetricError
}

func (p *Plugin) Collect() (err error) {
	var file *os.File
	if file, err = os.Open(procStatLocation); err != nil {
		return err
	}
	defer file.Close()

	var buf bytes.Buffer
	if _, err = buf.ReadFrom(file); err != nil {
		return
	}

	for _, cpu := range p.cpus {
		cpu.status = cpuStatusOffline
	}

	scanner := bufio.NewScanner(&buf)
	for scanner.Scan() {
		line := scanner.Text()
		if !strings.HasPrefix(line, "cpu") {
			continue
		}
		fields := strings.Fields(line)
		var index, status int
		if len(fields[0]) > 3 {
			var i int64
			if i, err = strconv.ParseInt(fields[0][3:], 10, 32); err != nil {
				return
			}

			if index = int(i); index < 0 {
				p.Debugf("invalid CPU index %d", index)
				continue
			}

			p.addCpu(index)

			status = cpuStatusOnline
		} else {
			index = -1
		}

		cpu := p.cpus[index+1]
		cpu.status = status

		slot := &cpu.history[cpu.tail]
		num := len(slot.counters)
		if num > len(fields)-1 {
			num = len(fields) - 1
		}
		for i := 0; i < num; i++ {
			slot.counters[i], _ = strconv.ParseUint(fields[i+1], 10, 64)
		}
		for i := num; i < len(slot.counters); i++ {
			slot.counters[i] = 0
		}
		// Linux includes guest times in user and nice times
		slot.counters[counterUser] -= slot.counters[counterGcpu]
		slot.counters[counterNice] -= slot.counters[counterGnice]

		if cpu.tail = cpu.tail.inc(); cpu.tail == cpu.head {
			cpu.head = cpu.head.inc()
		}
	}
	return nil
}

func (p *Plugin) addCpu(index int) {
	if p == nil || p.cpus == nil {
		return
	}

	if index == 0 {
		return
	}

	if index+1 >= len(p.cpus) {
		for idx := p.cpus[len(p.cpus)-1].index; idx < index; idx++ {
			p.cpus = append(p.cpus, &cpuUnit{index: idx + 1, status: cpuStatusOffline})
		}
	}
}

func numCPUConf() int {
	log.Tracef("Calling C function \"sysconf()\"")
	return int(C.sysconf(C._SC_NPROCESSORS_CONF))
}

func numCPUOnline() int {
	log.Tracef("Calling C function \"sysconf()\"")
	return int(C.sysconf(C._SC_NPROCESSORS_ONLN))
}

func (p *Plugin) Start() {
	p.cpus = p.newCpus(numCPUConf())
}

func (p *Plugin) Stop() {
	p.cpus = nil
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

func init() {
	plugin.RegisterMetrics(&impl, pluginName,
		"system.cpu.discovery", "List of detected CPUs/CPU cores, used for low-level discovery.",
		"system.cpu.num", "Number of CPUs.",
		"system.cpu.util", "CPU utilisation percentage.")
}
