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

package uptime

import (
	"bufio"
	"errors"
	"fmt"
	"strconv"
	"strings"
	"time"

	"golang.zabbix.com/sdk/std"
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
