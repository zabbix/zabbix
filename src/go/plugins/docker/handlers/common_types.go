/*
** Zabbix
** Copyright (C) 2001-2026 Zabbix SIA
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

package handlers

import (
	"strconv"
	"time"

	"golang.zabbix.com/sdk/log"
)

type unixTime int64

// UnmarshalJSON is used to convert time to unixtime.
func (tm *unixTime) UnmarshalJSON(b []byte) error {
	s, err := strconv.Unquote(string(b))
	if err != nil {
		// This should not be like that, but because it has been like that for a long time,
		// it is better to log but don't break yet.
		log.Warningf("[Docker plugin] WARNING: failed to unquote timestamp: %v (data: %s)", err, string(b))
		return nil //nolint:nlreturn    // Temporary: maintain old behavior.
	}

	t, err := time.Parse(time.RFC3339, s)
	if err != nil {
		log.Warningf("[Docker plugin] WARNING: failed to parse timestamp: %v (data: %s)", err, s)
		return nil //nolint:nlreturn  // Temporary: maintain old behavior
	}

	*tm = unixTime(t.Unix())

	return nil
}
