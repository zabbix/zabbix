// +build windows

/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

import (
	"syscall"
	"unsafe"
)

var (
	hKernel32 Hlib

	globalMemoryStatusEx uintptr
)

func init() {
	hKernel32 = mustLoadLibrary("kernel32.dll")

	globalMemoryStatusEx = hKernel32.mustGetProcAddress("GlobalMemoryStatusEx")
}

func GlobalMemoryStatusEx() (m *MEMORYSTATUSEX, err error) {
	m = &MEMORYSTATUSEX{}
	m.Length = uint32(unsafe.Sizeof(*m))
	ret, _, syserr := syscall.Syscall(globalMemoryStatusEx, 1, uintptr(unsafe.Pointer(m)), 0, 0)
	if ret == 0 {
		return nil, syserr
	}
	return
}
