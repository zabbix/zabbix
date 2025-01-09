//go:build !windows
// +build !windows

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

package proc

import (
	"syscall"
	"testing"
)

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
