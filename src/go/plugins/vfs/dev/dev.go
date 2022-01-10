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

package vfsdev

import (
	"errors"
	"sync"
	"time"

	"zabbix.com/pkg/plugin"
)

// Plugin -
type Plugin struct {
	plugin.Base
	devices map[string]*devUnit
	mutex   sync.Mutex
}

var impl Plugin

const (
	maxInactivityPeriod = time.Hour * 3
	maxHistory          = 60*15 + 1
)

const (
	ioModeRead = iota
	ioModeWrite
)

const (
	statTypeSectors = iota
	statTypeOperations
	statTypeSPS
	statTypeOPS
)

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

type devIO struct {
	sectors    uint64
	operations uint64
}

type devStats struct {
	clock int64
	rx    devIO
	tx    devIO
}

type devUnit struct {
	name       string
	head, tail historyIndex
	accessed   time.Time
	history    [maxHistory]devStats
}

func (p *Plugin) Collect() (err error) {
	now := time.Now()
	p.mutex.Lock()
	for key, dev := range p.devices {
		if now.Sub(dev.accessed) > maxInactivityPeriod {
			p.Debugf(`removed unused device "%s" disk collector `, dev.name)
			delete(p.devices, key)
			continue
		}
	}
	err = p.collectDeviceStats(p.devices)
	p.mutex.Unlock()
	return
}

func (p *Plugin) Period() int {
	return 1
}

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	var mode int
	switch key {
	case "vfs.dev.read":
		mode = ioModeRead
	case "vfs.dev.write":
		mode = ioModeWrite
	case "vfs.dev.discovery":
		return p.getDiscovery()
	default:
		return nil, plugin.UnsupportedMetricError
	}

	statType := statTypeSPS
	statRange := historyIndex(60)
	var devParam string

	switch len(params) {
	case 3:
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
		switch params[1] {
		case "", "sps":
			statType = statTypeSPS
		case "ops":
			statType = statTypeOPS
		case "sectors":
			statType = statTypeSectors
		case "operations":
			statType = statTypeOperations
		default:
			return nil, errors.New("Invalid second parameter.")
		}
		fallthrough
	case 1:
		devParam = params[0]
		if devParam == "all" {
			devParam = ""
		}
	case 0:
	default:
		return nil, errors.New("Too many parameters.")
	}

	if statType == statTypeSectors || statType == statTypeOperations {
		if len(params) > 2 {
			return nil, errors.New("Invalid number of parameters.")
		}
		var stats *devStats
		if stats, err = p.getDeviceStats(devParam); err != nil {
			return
		} else {
			if stats == nil {
				return nil, errors.New("Cannot obtain disk information.")
			}
			var devio *devIO
			if mode == ioModeRead {
				devio = &stats.rx
			} else {
				devio = &stats.tx
			}
			if statType == statTypeSectors {
				return devio.sectors, nil
			}
			return devio.operations, nil
		}
	}

	if ctx == nil {
		return nil, errors.New("This item is available only in daemon mode.")
	}

	var devName string
	if devName, err = p.getDeviceName(devParam); err != nil {
		p.Debugf("cannot find device name: %s", err)
		return nil, errors.New("Cannot obtain device name used internally by the kernel.")
	}

	now := time.Now()
	p.mutex.Lock()
	defer p.mutex.Unlock()

	if dev, ok := p.devices[devName]; ok {
		dev.accessed = now
		totalnum := dev.tail - dev.head
		if totalnum < 0 {
			totalnum += maxHistory
		}
		if totalnum < 2 {
			return
		}
		if totalnum < statRange {
			statRange = totalnum
		}
		tail := &dev.history[dev.tail.dec()]
		head := &dev.history[dev.tail.sub(statRange)]

		var tailio, headio *devIO
		if mode == ioModeRead {
			tailio = &tail.rx
			headio = &head.rx
		} else {
			tailio = &tail.tx
			headio = &head.tx
		}
		if statType == statTypeSPS {
			return float64(tailio.sectors-headio.sectors) * float64(time.Second) / float64(tail.clock-head.clock), nil
		}
		return float64(tailio.operations-headio.operations) * float64(time.Second) / float64(tail.clock-head.clock), nil
	} else {
		p.devices[devName] = &devUnit{name: devName, accessed: now}
		return
	}
}

func init() {
	impl.devices = make(map[string]*devUnit)
	plugin.RegisterMetrics(&impl, "VFSDev",
		"vfs.dev.read", "Disk read statistics.",
		"vfs.dev.write", "Disk write statistics.",
		"vfs.dev.discovery", "List of block devices and their type. Used for low-level discovery.")
}
