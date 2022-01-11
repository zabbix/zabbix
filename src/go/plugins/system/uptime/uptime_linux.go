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

package uptime

import (
	"bufio"
	"errors"
	"fmt"
	"strconv"
	"strings"
	"time"

	"zabbix.com/pkg/std"
)

func getUptime() (uptime int, err error) {
	var file std.File
	if file, err = stdOs.Open("/proc/stat"); err != nil {
		err = fmt.Errorf("Cannot read boot time: %s", err.Error())
		return
	}
	defer file.Close()

	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		if strings.HasPrefix(scanner.Text(), "btime") {
			var boot int
			if boot, err = strconv.Atoi(scanner.Text()[6:]); err != nil {
				return
			}
			return int(time.Now().Unix()) - boot, nil
		}
	}

	return 0, errors.New("Cannot locate boot time")
}
