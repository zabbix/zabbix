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

package memory

import (
	"bytes"
	"errors"
	"fmt"
	"strconv"
	"strings"
	"syscall"

	"zabbix.com/pkg/plugin"
)

const (
	kB = 1024
	mB = kB * 1024
	gB = mB * 1024
	tB = gB * 1024
)

func (p *Plugin) exportVMMemorySize(mode string) (result interface{}, err error) {
	var mem float64
	switch mode {
	case "total", "":
		mem, err = getMemory("MemTotal")
	case "free":
		mem, err = getMemory("MemFree")
	case "buffers":
		mem, err = getMemory("Buffers")
	case "used":
		mem, err = getUsed(false)
	case "pused":
		mem, err = getUsed(true)
	case "available":
		mem, err = getAvailable(false)
	case "pavailable":
		mem, err = getAvailable(true)
	case "shared":
		return nil, plugin.UnsupportedMetricError
	case "cached":
		mem, err = getMemory("Cached")
	case "active":
		mem, err = getMemory("Active")
	case "anon":
		mem, err = getMemory("AnonPages")
	case "inactive":
		mem, err = getMemory("Inactive")
	case "slab":
		mem, err = getMemory("Slab")
	default:
		return nil, errors.New("Invalid first parameter.")
	}

	if mode == "pused" || mode == "pavailable" {
		return mem, nil
	}

	result = int(mem)

	return
}

func getUsed(percent bool) (float64, error) {
	total, err := getMemory("MemTotal")
	if err != nil {
		return 0, err
	}
	free, err := getMemory("MemFree")
	if err != nil {
		return 0, err
	}

	if percent {
		return (total - free) / total * float64(100), nil

	}

	return total - free, nil
}

func getAvailable(percent bool) (float64, error) {
	mem, err := getMemory("MemAvailable")
	if err != nil {
		cached, err := getMemory("Cached")
		if err != nil {
			return 0, err
		}
		free, err := getMemory("MemFree")
		if err != nil {
			return 0, err
		}
		buff, err := getMemory("Buffers")
		if err != nil {
			return 0, err
		}
		mem = (free + buff) + cached
	}

	if percent {
		total, err := getMemory("MemTotal")
		if err != nil {
			return 0, err
		}
		return mem / total * float64(100), nil
	}

	return mem, nil
}

func getMemory(memType string) (mem float64, err error) {
	meminfo, err := readAll("/proc/meminfo")
	if err != nil {
		return mem, fmt.Errorf("cannot read meminfo file: %s", err.Error())
	}

	var found bool
	mem, found, err = byteFromProcFileData(meminfo, memType)
	if err != nil {
		return mem, fmt.Errorf("cannot get the memory amount for %s: %s", memType, err.Error())
	}

	if !found {
		return mem, fmt.Errorf("cannot get the memory amount for %s", memType)
	}

	return
}

func readAll(filename string) (data []byte, err error) {
	fd, err := syscall.Open(filename, syscall.O_RDONLY, 0)
	if err != nil {
		return
	}
	defer syscall.Close(fd)
	var buf bytes.Buffer
	b := make([]byte, 2048)
	for {
		var n int
		if n, err = syscall.Read(fd, b); err != nil {
			return
		}
		if n == 0 {
			return buf.Bytes(), nil
		}
		if _, err = buf.Write(b[:n]); err != nil {
			return
		}
	}
}

func byteFromProcFileData(data []byte, valueName string) (float64, bool, error) {
	for _, line := range strings.Split(string(data), "\n") {
		i := strings.Index(line, ":")
		if i < 0 || valueName != line[:i] {
			continue
		}

		line = line[i+1:]
		if len(line) < 3 {
			continue
		}

		v, err := strconv.Atoi(strings.TrimSpace(line[:len(line)-2]))
		if err != nil {
			return 0, false, err
		}

		switch line[len(line)-2:] {
		case "kB":
			v *= kB
		case "mB":
			v *= mB
		case "GB":
			v *= gB
		case "TB":
			v *= tB
		default:
			return 0, false, errors.New("cannot resolve value type")
		}
		return float64(v), true, nil
	}

	return 0, false, nil
}
