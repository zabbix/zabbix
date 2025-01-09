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
