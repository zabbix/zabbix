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
	"golang.zabbix.com/agent2/pkg/pdh"
)

func getUptime() (uptime int, err error) {
	var value *int64
	value, err = pdh.GetCounterInt64(pdh.CounterPath(pdh.ObjectSystem, pdh.CounterSystemUptime))
	if err != nil || value == nil {
		return
	}
	return int(*value), nil
}
