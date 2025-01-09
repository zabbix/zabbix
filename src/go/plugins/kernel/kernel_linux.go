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

package kernel

import (
	"bufio"
	"fmt"
	"strconv"
	"strings"
)

// getFirstNum  - get first number from file
func getFirstNum(key string) (max uint64, err error) {
	fileName := "/proc"
	switch key {
	case "kernel.maxproc":
		fileName += "/sys/kernel/pid_max"
	case "kernel.maxfiles":
		fileName += "/sys/fs/file-max"
	case "kernel.openfiles":
		fileName += "/sys/fs/file-nr"
	}

	file, err := stdOs.Open(fileName)
	if err == nil {
		var line []byte
		var long bool

		reader := bufio.NewReader(file)

		if line, long, err = reader.ReadLine(); err == nil && !long {
			if key == "kernel.openfiles" {
				max, err = strconv.ParseUint(strings.Split(string(line), "\t")[0], 10, 64)
			} else {
				max, err = strconv.ParseUint(string(line), 10, 64)
			}
		}

		file.Close()
	}

	if err != nil {
		err = fmt.Errorf("Cannot obtain data from %s.", fileName)
	}

	return
}
