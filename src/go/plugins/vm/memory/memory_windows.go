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

package memory

import (
	"errors"

	"golang.zabbix.com/agent2/pkg/win32"
)

func (p *Plugin) exportVMMemorySize(mode string) (result interface{}, err error) {
	if mode == "cached" {
		pinfo, err := win32.GetPerformanceInfo()
		if err != nil {
			return nil, err
		}
		return uint64(pinfo.SystemCache) * uint64(pinfo.PageSize), nil
	}

	mem, err := win32.GlobalMemoryStatusEx()
	if err != nil {
		return nil, err
	}
	switch mode {
	case "", "total":
		return mem.TotalPhys, nil
	case "free", "available":
		return mem.AvailPhys, nil
	case "used":
		return mem.TotalPhys - mem.AvailPhys, nil
	case "pused":
		return float64(mem.TotalPhys-mem.AvailPhys) / float64(mem.TotalPhys) * 100, nil
	case "pavailable":
		return float64(mem.AvailPhys) / float64(mem.TotalPhys) * 100, nil
	default:
		return nil, errors.New("Invalid first parameter.")
	}
}
