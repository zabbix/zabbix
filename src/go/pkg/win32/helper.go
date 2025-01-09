//go:build windows
// +build windows

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
