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
	"fmt"
	"syscall"
)

func mustLoadLibrary(name string) Hlib {
	if handle, err := syscall.LoadLibrary(name); err != nil {
		panic(err.Error())
	} else {
		return Hlib(handle)
	}
}

func (h Hlib) getProcAddress(name string) (uintptr, error) {
	return syscall.GetProcAddress(syscall.Handle(h), name)
}

func (h Hlib) mustGetProcAddress(name string) uintptr {
	if addr, err := syscall.GetProcAddress(syscall.Handle(h), name); err != nil {
		panic(fmt.Sprintf("Failed to get function %s: %s", name, err.Error()))
	} else {
		return addr
	}
}

func bool2uintptr(value bool) uintptr {
	if value {
		return 1
	}
	return 0
}

func init() {
}
