/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
	"fmt"
	"sync"
	"time"
	"zabbix/internal/plugin"
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
	head, tail int
	accessed   time.Time
	history    []devStats
}

var typeParams map[string]int = map[string]int{
	"":           statTypeSPS,
	"sps":        statTypeSPS,
	"sectors":    statTypeSectors,
	"operations": statTypeOperations,
}

var rangeParams map[string]int = map[string]int{
	"":      60,
	"avg1":  60,
	"avg5":  60 * 5,
	"avg15": 60 * 15,
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
		return nil, errors.New("Unsupported metric")
	}

	var devParam, typeParam, rangeParam string
	switch len(params) {
	case 3:
		rangeParam = params[2]
		fallthrough
	case 2:
		typeParam = params[1]
		fallthrough
	case 1:
		devParam = params[0]
		if devParam == "all" {
			devParam = ""
		}
	default:
		return nil, errors.New("Too many parameters.")
	}

	var ok bool
	var statType int
	if statType, ok = typeParams[typeParam]; !ok {
		return nil, errors.New("Invalid second parameter.")
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
				return nil, errors.New("Device not found.")
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

	var statRange int
	if statRange, ok = rangeParams[rangeParam]; !ok {
		return nil, errors.New("Invalid third parameter.")
	}

	var devName string
	if devName, err = p.getDeviceName(devParam); err != nil {
		return nil, fmt.Errorf("Cannot obtain device name: %s", err)
	}

	p.mutex.Lock()
	defer p.mutex.Unlock()

	if dev, ok := p.devices[devName]; ok {
		// TODO: retrieve dev statistics
		return nil, fmt.Errorf("Not implemented. %v %d %d", *dev, statRange, mode)
	} else {
		p.devices[devName] = &devUnit{name: devName, accessed: time.Now(), history: make([]devStats, maxHistory)}
		return
	}
}

func init() {
	impl.devices = make(map[string]*devUnit)
	plugin.RegisterMetric(&impl, "vfsdev", "vfs.dev.read", "Disk read statistics.")
	plugin.RegisterMetric(&impl, "vfsdev", "vfs.dev.write", "Disk write statistics.")
	plugin.RegisterMetric(&impl, "vfsdev", "vfs.dev.discovery", "List of block devices and their type."+
		" Used for low-level discovery.")
}
