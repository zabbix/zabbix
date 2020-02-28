/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

package kernel

import (
	"bufio"
	"fmt"
	"strconv"
)

func getMax(proc bool) (max uint64, err error) {
	var fileName string

	if proc {
		fileName = "/proc/sys/kernel/pid_max"
	} else {
		fileName = "/proc/sys/fs/file-max"
	}

	file, err := stdOs.Open(fileName)
	if err == nil {
		var line []byte
		var long bool

		reader := bufio.NewReader(file)

		if line, long, err = reader.ReadLine(); err == nil && !long {
			max, err = strconv.ParseUint(string(line), 10, 64)
		}

		file.Close()
	}

	if err != nil {
		err = fmt.Errorf("Cannot obtain data from %s.", fileName)
	}

	return
}
