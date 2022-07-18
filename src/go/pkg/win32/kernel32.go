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
	"fmt"
	"syscall"
	"unsafe"

	"golang.org/x/sys/windows"
)

var (
	hKernel32 Hlib

	globalMemoryStatusEx             uintptr
	getProcessIoCounters             uintptr
	getLogicalProcessorInformationEx uintptr
	getActiveProcessorGroupCount     uintptr
	getDiskFreeSpaceW                uintptr
	getVolumePathNameW               uintptr
)

const (
	RelationProcessorCore = iota
	RelationNumaNode
	RelationCache
	RelationProcessorPackage
	RelationGroup
	RelationAll = 0xfff
)

func init() {
	hKernel32 = mustLoadLibrary("kernel32.dll")

	globalMemoryStatusEx = hKernel32.mustGetProcAddress("GlobalMemoryStatusEx")
	getProcessIoCounters = hKernel32.mustGetProcAddress("GetProcessIoCounters")
	getLogicalProcessorInformationEx = hKernel32.mustGetProcAddress("GetLogicalProcessorInformationEx")
	getActiveProcessorGroupCount = hKernel32.mustGetProcAddress("GetActiveProcessorGroupCount")
	getDiskFreeSpaceW = hKernel32.mustGetProcAddress("GetDiskFreeSpaceW")
	getVolumePathNameW = hKernel32.mustGetProcAddress("GetVolumePathNameW")
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

func GetProcessIoCounters(proc syscall.Handle) (ioc *IO_COUNTERS, err error) {
	ioc = &IO_COUNTERS{}
	ret, _, syserr := syscall.Syscall(getProcessIoCounters, 2, uintptr(proc), uintptr(unsafe.Pointer(ioc)), 0)
	if ret == 0 {
		return nil, syserr
	}
	return
}

func GetLogicalProcessorInformationEx(relation int, info []byte) (size uint32, err error) {
	var buffer uintptr
	if info != nil {
		size = uint32(len(info))
		buffer = uintptr(unsafe.Pointer(&info[0]))
	}
	ret, _, syserr := syscall.Syscall(getLogicalProcessorInformationEx, 3, uintptr(relation),
		buffer, uintptr(unsafe.Pointer(&size)))
	if ret == 0 {
		if syscall.Errno(syserr) != syscall.ERROR_INSUFFICIENT_BUFFER {
			return 0, syserr
		}
	}
	return
}

func GetActiveProcessorGroupCount() (count int) {
	ret, _, _ := syscall.Syscall(getActiveProcessorGroupCount, 0, 0, 0, 0)
	return int(ret)
}

func GetFullPathName(path string) (string, uint32, error) {
	var wPath []uint16
	wPath, err := windows.UTF16FromString(path)
	if err != nil {
		return "", 0, err
	}

	buf := make([]uint16, windows.MAX_PATH)

	len, err := syscall.GetFullPathName(&wPath[0], windows.MAX_PATH, &buf[0], nil)
	if err != nil {
		return "", 0, err
	}

	return syscall.UTF16ToString(buf), len, nil
}

func GetVolumePathName(path string, len uint32) (disk string, err error) {
	var wPath []uint16
	wPath, err = windows.UTF16FromString(path)
	if err != nil {
		return
	}

	wdisk := make([]uint16, len)

	ret, _, err := syscall.Syscall(
		getVolumePathNameW,
		3,
		uintptr(unsafe.Pointer(&wPath)),
		uintptr(unsafe.Pointer(&wdisk[0])),
		uintptr(unsafe.Pointer(&len)),
	)

	if ret == 0 {
		return "", fmt.Errorf("failed to get volume name for path %s, %s", path, err.Error())
	}

	return syscall.UTF16ToString(wdisk), nil
}

func GetDiskFreeSpace(path string) (c CLUSTER, err error) {
	var p []uint16
	p, err = windows.UTF16FromString(path)
	if err != nil {
		return
	}

	ptrPath := uintptr(unsafe.Pointer(&p[0]))
	var ret uintptr

	ret, _, err = syscall.Syscall6(
		getDiskFreeSpaceW,
		6,
		ptrPath,
		uintptr(unsafe.Pointer(&c.LpSectorsPerCluster)),
		uintptr(unsafe.Pointer(&c.LpBytesPerSector)),
		uintptr(unsafe.Pointer(&c.LpNumberOfFreeClusters)),
		uintptr(unsafe.Pointer(&c.LpTotalNumberOfClusters)),
		0)
	if ret == 0 {
		return c, fmt.Errorf("failed to get disk free space for path :%s, %s", path, err.Error())
	}

	return c, nil
}
