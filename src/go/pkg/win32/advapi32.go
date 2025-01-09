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

	"golang.org/x/sys/windows"
)

var (
	hAdvapi32 Hlib

	getServiceKeyName uintptr
)

func init() {
	hAdvapi32 = mustLoadLibrary("advapi32.dll")

	getServiceKeyName = hAdvapi32.mustGetProcAddress("GetServiceKeyNameW")
}

func GetServiceKeyName(h syscall.Handle, displayName string) (name string, err error) {
	wdn, err := windows.UTF16FromString(displayName)
	if err != nil {
		return
	}
	b := make([]uint16, 4096)
	size := uint32(len(b))
	ret, _, syserr := syscall.Syscall6(getServiceKeyName, 4, uintptr(h), uintptr(unsafe.Pointer(&wdn[0])),
		uintptr(unsafe.Pointer(&b[0])), uintptr(unsafe.Pointer(&size)), 0, 0)
	if ret == 0 {
		return "", syserr
	}
	return windows.UTF16ToString(b), nil
}
