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

package dns

import (
	"fmt"
	"os"
	"strings"
)

func (o *options) setDefaultIP() (err error) {
	data, err := os.ReadFile("/etc/resolv.conf")
	if err != nil {
		return
	}

	s := strings.Split(string(data), "\n")
	for _, tmp := range s {
		if strings.HasPrefix(tmp, "nameserver") {
			return o.setIP(strings.TrimSpace(strings.TrimPrefix(tmp, "nameserver")))
		}
	}

	return fmt.Errorf("cannot find default dns nameserver")
}
