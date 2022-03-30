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

package win32

func NextField(buf []uint16) (field []uint16, left []uint16) {
	start := -1
	for i, c := range buf {
		if c != 0 {
			start = i
			break
		}
	}
	if start == -1 {
		return []uint16{}, []uint16{}
	}
	for i, c := range buf[start:] {
		if c == 0 {
			return buf[start : start+i], buf[start+i+1:]
		}
	}
	return buf[start:], []uint16{}
}
