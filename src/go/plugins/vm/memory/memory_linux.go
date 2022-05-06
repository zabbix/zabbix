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

package memory

import (
	"errors"

	"git.zabbix.com/ap/plugin-support/zbxerr"
	"zabbix.com/pkg/procfs"
)

func (p *Plugin) exportVMMemorySize(mode string) (result interface{}, err error) {
	switch mode {
	case "total", "":
		result, err = procfs.GetMemory("MemTotal")
	case "free":
		result, err = procfs.GetMemory("MemFree")
	case "buffers":
		result, err = procfs.GetMemory("Buffers")
	case "used":
		result, _, err = getUsedAndTotal()
	case "pused":
		result, err = getPUsed()
	case "available":
		result, err = getAvailable()
	case "pavailable":
		result, err = getPAvailable()
	case "cached":
		result, err = procfs.GetMemory("Cached")
	case "active":
		result, err = procfs.GetMemory("Active")
	case "anon":
		result, err = procfs.GetMemory("AnonPages")
	case "inactive":
		result, err = procfs.GetMemory("Inactive")
	case "slab":
		result, err = procfs.GetMemory("Slab")
	default:
		return nil, errors.New("Invalid first parameter.")
	}

	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	return
}

func getUsedAndTotal() (uint64, uint64, error) {
	total, err := procfs.GetMemory("MemTotal")
	if err != nil {
		return 0, 0, err
	}

	free, err := procfs.GetMemory("MemFree")
	if err != nil {
		return 0, 0, err
	}

	return total - free, total, nil
}

func getPUsed() (float64, error) {
	used, total, err := getUsedAndTotal()
	if err != nil {
		return 0, err
	}

	return float64(used) / float64(total) * 100, nil
}

func getAvailable() (uint64, error) {
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

	return mem, nil
}

func getPAvailable() (float64, error) {
	mem, err := getAvailable()
	if err != nil {
		return 0, err
	}

	total, err := procfs.GetMemory("MemTotal")
	if err != nil {
		return 0, err
	}

	return float64(mem) / float64(total) * 100, nil
}
