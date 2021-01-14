/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
	counterUtil
	counterLoad
)

type cpuCounters struct {
	load float64
	util float64
}

func counterByType(name string) (counter cpuCounter) {
	switch name {
	case "", "system":
		return counterUtil
	default:
		return counterUnknown
	}
}

func (c *cpuUnit) counterAverage(counter cpuCounter, period historyIndex) (value interface{}) {
	if c.head == c.tail {
		return
	}
	var tail, head *cpuCounters
	totalnum := c.tail - c.head
	if totalnum < 0 {
		totalnum += maxHistory
	}
	if totalnum < 2 {
		// need at least two samples to calculate change over period
		return
	}

	if totalnum-1 < period {
		period = totalnum - 1
	}
	tail = &c.history[c.tail.dec()]
	head = &c.history[c.tail.sub(period+1)]

	switch counter {
	case counterUtil:
		if tail.util < head.util {
			return
		} else {
			return (tail.util - head.util) / float64(period)
		}
	case counterLoad:
		if tail.load < head.load {
			return
		} else {
			return (tail.load - head.load) / float64(numCPU()) / float64(period)
		}
	}
	return
}
