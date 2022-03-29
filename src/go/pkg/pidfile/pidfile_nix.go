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

package pidfile

import (
	"fmt"
	"io"
	"os"
	"syscall"
)

func createPidFile(pid int, path string) (file *os.File, err error) {
	if path == "" {
		path = "/tmp/zabbix_agent2.pid"
	}

	flockT := syscall.Flock_t{
		Type:   syscall.F_WRLCK,
		Whence: io.SeekStart,
		Start:  0,
		Len:    0,
		Pid:    int32(pid),
	}
	if file, err = os.OpenFile(path, os.O_WRONLY|os.O_CREATE|syscall.O_CLOEXEC, 0644); nil != err {
		return nil, fmt.Errorf("cannot open PID file [%s]: %s", path, err.Error())
	}
	if err = syscall.FcntlFlock(file.Fd(), syscall.F_SETLK, &flockT); nil != err {
		file.Close()
		return nil, fmt.Errorf("Is this process already running? Could not lock PID file [%s]: %s",
			path, err.Error())
	}
	return
}
