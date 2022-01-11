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

const (
	counterUnknown cpuCounter = iota - 1
	counterUser
	counterNice
	counterSystem
	counterIdle
	counterIowait
	counterIrq
	counterSoftirq
	counterSteal
	counterGcpu
	counterGnice
	counterNum // number of cpu counters
)

type cpuCounters struct {
	counters [counterNum]uint64
}

func counterByType(name string) (counter cpuCounter) {
	switch name {
	case "", "user":
		return counterUser
	case "idle":
		return counterIdle
	case "nice":
		return counterNice
	case "system":
		return counterSystem
	case "iowait":
		return counterIowait
	case "interrupt":
		return counterIrq
	case "softirq":
		return counterSoftirq
	case "steal":
		return counterSteal
	case "guest":
		return counterGcpu
	case "guest_nice":
		return counterGnice
	default:
		return counterUnknown
	}
}

func (c *cpuUnit) counterAverage(counter cpuCounter, period historyIndex, _ int) (result interface{}) {
	if c.head == c.tail {
		return
	}
	var tail, head *cpuCounters
	totalnum := c.tail - c.head
	if totalnum < 0 {
		totalnum += maxHistory
	}
	if totalnum < 2 {
		// need at least two samples to calculate utilization
		return
	}
	if totalnum-1 < period {
		period = totalnum - 1
	}
	tail = &c.history[c.tail.dec()]
	head = &c.history[c.tail.sub(period+1)]

	var value, total uint64
	for i := 0; i < len(tail.counters); i++ {
		if tail.counters[i] > head.counters[i] {
			total += tail.counters[i] - head.counters[i]
		}
	}
	if total == 0 {
		return
	}

	if tail.counters[counter] > head.counters[counter] {
		value = tail.counters[counter] - head.counters[counter]
	}
	return float64(value) * 100 / float64(total)
}
