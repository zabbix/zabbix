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
