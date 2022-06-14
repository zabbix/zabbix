//go:build windows
// +build windows

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

package smart

import (
	"fmt"
	"time"

	"zabbix.com/pkg/zbxcmd"
)

func (p *Plugin) executeSmartctl(args string, strict bool) ([]byte, error) {
	path := "smartctl"

	if p.options.Path != "" {
		path = p.options.Path
	}

	var out string

	var err error

	executable := fmt.Sprintf("%s %s", path, args)

	p.Tracef("executing smartctl command: %s", executable)

	if strict {
		out, err = zbxcmd.ExecuteStrict(executable, time.Second*time.Duration(p.options.Timeout), "")
	} else {
		out, err = zbxcmd.Execute(executable, time.Second*time.Duration(p.options.Timeout), "")
	}

	p.Tracef("command %s smartctl raw response: %s", executable, out)

	return []byte(out), err
}
