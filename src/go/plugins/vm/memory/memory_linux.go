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
	"errors"

	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/procfs"
	"zabbix.com/pkg/zbxerr"
)

func (p *Plugin) exportVMMemorySize(mode string) (result interface{}, err error) {
	var mem float64

	switch mode {
	case "total", "":
		mem, err = procfs.GetMemory("MemTotal")
	case "free":
		mem, err = procfs.GetMemory("MemFree")
	case "buffers":
		mem, err = procfs.GetMemory("Buffers")
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
		mem, err = procfs.GetMemory("Cached")
	case "active":
		mem, err = procfs.GetMemory("Active")
	case "anon":
		mem, err = procfs.GetMemory("AnonPages")
	case "inactive":
		mem, err = procfs.GetMemory("Inactive")
	case "slab":
		mem, err = procfs.GetMemory("Slab")
	default:
		return nil, errors.New("Invalid first parameter.")
	}

	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	if mode == "pused" || mode == "pavailable" {
		return mem, nil
	}

	result = int(mem)

	return
}

func getUsed(percent bool) (float64, error) {
	total, err := procfs.GetMemory("MemTotal")
	if err != nil {
		return 0, err
	}

	free, err := procfs.GetMemory("MemFree")
	if err != nil {
		return 0, err
	}

	if percent {
		return (total - free) / total * float64(100), nil
	}

	return total - free, nil
}

func getAvailable(percent bool) (float64, error) {
	mem, err := procfs.GetMemory("MemAvailable")
	if err != nil {
		cached, err := procfs.GetMemory("Cached")
		if err != nil {
			return 0, err
		}

		free, err := procfs.GetMemory("MemFree")
		if err != nil {
			return 0, err
		}

		buff, err := procfs.GetMemory("Buffers")
		if err != nil {
			return 0, err
		}

		mem = (free + buff) + cached
	}

	if percent {
		total, err := procfs.GetMemory("MemTotal")
		if err != nil {
			return 0, err
		}

		return mem / total * float64(100), nil
	}

	return mem, nil
}
