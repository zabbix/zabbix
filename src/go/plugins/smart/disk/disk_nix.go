// +build !windows

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

package disk

import (
	"fmt"
	"strings"
	"time"

	"zabbix.com/pkg/zbxcmd"
)

func executeSmartctl(cmd string) (string, error) {
	return zbxcmd.Execute(cmd, time.Second*time.Duration(5*time.Second))
}

func deviceCount(name string) (int, error) {
	str, err := zbxcmd.Execute(fmt.Sprintf("ls -1 %s*", name), time.Second*time.Duration(5*time.Second))
	if err != nil {
		return 0, err
	}

	return len(strings.Split(str, "\n")), nil
}
