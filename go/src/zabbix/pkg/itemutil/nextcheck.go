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

package itemutil

import (
	"fmt"
	"strconv"
	"time"
)

func GetNextcheck(itemid uint64, delay string, unsupported bool, from time.Time) (nextcheck time.Time, err error) {
	var simple_delay int64
	// TODO: add flexible/scheduled interval support
	if simple_delay, err = strconv.ParseInt(delay, 10, 64); err != nil {
		err = fmt.Errorf("cannot parse item delay: %s", err)
		return
	}

	from_seconds := from.Unix()
	return time.Unix(from_seconds-from_seconds%simple_delay+simple_delay, 0), nil
}
