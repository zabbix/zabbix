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
	"os"
	"strconv"
)

type File struct {
	file *os.File
}

func New(path string) (pidFile *File, err error) {
	var pf File
	pid := os.Getpid()
	if pf.file, err = createPidFile(pid, path); err != nil || pf.file == nil {
		return
	}
	if err = pf.file.Truncate(0); err != nil {
		return
	}
	if _, err = pf.file.WriteString(strconv.Itoa(pid)); err != nil {
		return
	}
	if err = pf.file.Sync(); err != nil {
		return
	}

	return &pf, nil
}

func (f *File) Delete() {
	if nil == f || nil == f.file {
		return
	}
	path := f.file.Name()
	f.file.Close()
	os.Remove(path)
}
