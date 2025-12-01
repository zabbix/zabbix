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

package handlers

import (
	"strconv"
	"time"
)

type unixTime int64

// UnmarshalJSON is used to convert time to unixtime
func (tm *unixTime) UnmarshalJSON(b []byte) error {
	s, err := strconv.Unquote(string(b))
	if err != nil {
		return nil
	}

	t, err := time.Parse(time.RFC3339, s)
	if err != nil {
		return nil
	}

	*tm = unixTime(t.Unix())

	return err
}
