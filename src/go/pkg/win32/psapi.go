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
)

var (
	hPsapi Hlib

	getProcessMemoryInfo uintptr
	getPerformanceInfo   uintptr
)

func init() {
	hPsapi = mustLoadLibrary("psapi.dll")

	getProcessMemoryInfo = hPsapi.mustGetProcAddress("GetProcessMemoryInfo")
	getPerformanceInfo = hPsapi.mustGetProcAddress("GetPerformanceInfo")
}

func GetProcessMemoryInfo(proc syscall.Handle) (mem *PROCESS_MEMORY_COUNTERS_EX, err error) {
	mem = &PROCESS_MEMORY_COUNTERS_EX{}
	mem.Cb = uint32(unsafe.Sizeof(*mem))
	ret, _, syserr := syscall.Syscall(getProcessMemoryInfo, 3, uintptr(proc), uintptr(unsafe.Pointer(mem)), uintptr(mem.Cb))
	if ret == 0 {
		return nil, syserr
	}
	return
}

func GetPerformanceInfo() (pinfo *PERFORMANCE_INFORMATION, err error) {
	pinfo = &PERFORMANCE_INFORMATION{}
	pinfo.Cb = uint32(unsafe.Sizeof(*pinfo))
	ret, _, syserr := syscall.Syscall(getPerformanceInfo, 2, uintptr(unsafe.Pointer(pinfo)), uintptr(pinfo.Cb), 0)
	if ret == 0 {
		return nil, syserr
	}
	return
}
