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

import (
	"syscall"
	"unsafe"

	"golang.org/x/sys/windows"
)

var (
	hIphlp Hlib

	getIfTable2    uintptr
	freeMibTable   uintptr
	getIpAddrTable uintptr
	getTcpTable    uintptr
)

func init() {
	hIphlp = mustLoadLibrary("iphlpapi.dll")

	getIfTable2 = hIphlp.mustGetProcAddress("GetIfTable2")
	freeMibTable = hIphlp.mustGetProcAddress("FreeMibTable")
	getIpAddrTable = hIphlp.mustGetProcAddress("GetIpAddrTable")
	getTcpTable = hIphlp.mustGetProcAddress("GetTcpTable")
}

func GetIfTable2() (table *MIB_IF_TABLE2, err error) {
	ret, _, syserr := syscall.Syscall(getIfTable2, 1, uintptr(unsafe.Pointer(&table)), 0, 0)
	if ret != windows.NO_ERROR {
		return nil, syserr
	}
	return
}

func FreeMibTable(table *MIB_IF_TABLE2) {
	_, _, _ = syscall.Syscall(freeMibTable, 1, uintptr(unsafe.Pointer(table)), 0, 0)
}

// GetIpAddrTable calls win32 GetIpAddrTable function. Check if the returned size was less or equal to input size
// to determine if the buffer was large enough for the address table.
func GetIpAddrTable(table *MIB_IPADDRTABLE, sizeIn uint32, order bool) (sizeOut uint32, err error) {
	sizeOut = sizeIn
	ret, _, syserr := syscall.Syscall(getIpAddrTable, 3, uintptr(unsafe.Pointer(table)),
		uintptr(unsafe.Pointer(&sizeOut)), bool2uintptr(order))

	if ret != windows.NO_ERROR {
		if syscall.Errno(ret) != syscall.ERROR_INSUFFICIENT_BUFFER {
			return 0, syserr
		}
	}
	return
}

// GetTcpTable calls win32 GetTcpTable function. Check if the returned size was less or equal to input size
// to determine if the buffer was large enough for the address table.
func GetTcpTable(table *MIB_TCPTABLE, sizeIn uint32, order bool) (sizeOut uint32, err error) {
	sizeOut = sizeIn
	ret, _, syserr := syscall.Syscall(getTcpTable, 3, uintptr(unsafe.Pointer(table)),
		uintptr(unsafe.Pointer(&sizeOut)), bool2uintptr(order))

	if ret != windows.NO_ERROR {
		if syscall.Errno(ret) != syscall.ERROR_INSUFFICIENT_BUFFER {
			return 0, syserr
		}
	}
	return
}
