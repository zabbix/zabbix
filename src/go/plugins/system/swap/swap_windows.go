//go:build windows
// +build windows

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

package swap

import (
	"zabbix.com/pkg/win32"
)

func getSwap() (uint64, uint64, error) {
	m, err := win32.GlobalMemoryStatusEx()
	if err != nil {
		return 0, 0, nil
	}

	var total, avail uint64
	if m.TotalPageFile > m.TotalPhys {
		total = m.TotalPageFile - m.TotalPhys
	}
	if m.AvailPageFile > m.AvailPhys {
		avail = m.AvailPageFile - m.AvailPhys
	}

	return total, avail, nil
}
