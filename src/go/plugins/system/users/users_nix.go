//go:build !windows

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

package users

import (
	"strconv"
	"time"

	"golang.zabbix.com/agent2/pkg/zbxcmd"
	"golang.zabbix.com/sdk/errs"
)

func (p *Plugin) getUsersNum(timeout int) (int, error) {
	if p.executor == nil {
		var err error

		p.executor, err = zbxcmd.InitExecutor()
		if err != nil {
			return 0, errs.Wrap(err, "command init failed")
		}
	}

	out, err := p.executor.Execute("who | wc -l", time.Second*time.Duration(timeout), "")
	if err != nil {
		return 0, errs.Wrap(err, "failed to execute command")
	}

	return strconv.Atoi(out)
}
