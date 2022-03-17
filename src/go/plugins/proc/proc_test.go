//go:build !windows
// +build !windows

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

package proc

import (
	"syscall"
	"testing"
)

func BenchmarkRead2k(b *testing.B) {
	for i := 0; i < b.N; i++ {
		_, _ = read2k("/proc/self/stat")
	}
}

func BenchmarkSyscallRead(b *testing.B) {
	for i := 0; i < b.N; i++ {
		buffer := make([]byte, 2048)
		fd, err := syscall.Open("/proc/self/stat", syscall.O_RDONLY, 0)
		if err != nil {
			return
		}

		syscall.Read(fd, buffer)
		syscall.Close(fd)
	}
}
