/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package maxfiles

import (
	"bufio"
	"fmt"
	"strconv"
	"zabbix/pkg/std"
)

func getMaxfiles() (maxfiles uint64, err error) {
	var file std.File
	var line string

	file, err = stdOs.Open("/proc/sys/fs/file-max")
	if err != nil {
		return 0, fmt.Errorf("Cannot read /proc/sys/fs/file-max: %s", err.Error())
	}
	defer file.Close()

	reader := bufio.NewReader(file)
	line, err = reader.ReadString('\n')
	if err != nil {
		return 0, fmt.Errorf("Cannot read number of files: %s", err.Error())
	}

	maxfiles, err = strconv.ParseUint(line[:len(line)-1], 10, 64)
	if err != nil {
		return 0, fmt.Errorf("Cannot read number of files: %s", err.Error())
	}

	return maxfiles, nil
}
