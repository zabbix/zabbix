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
)

var (
	hUser32 Hlib

	getGuiResources uintptr
)

func init() {
	hUser32 = mustLoadLibrary("user32.dll")

	getGuiResources = hUser32.mustGetProcAddress("GetGuiResources")
}

func GetGuiResources(proc syscall.Handle, flags uint32) (num uint32) {
	ret, _, _ := syscall.Syscall(getGuiResources, 3, uintptr(proc), uintptr(flags), 0)
	return uint32(ret)
}
