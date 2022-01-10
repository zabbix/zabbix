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

package redis

import (
	"github.com/mediocregopher/radix/v3"
)

const (
	pingFailed = 0
	pingOk     = 1
)

// pingHandler executes 'PING' command and returns pingOk if a connection is alive or pingFailed otherwise.
func pingHandler(conn redisClient, _ map[string]string) (interface{}, error) {
	var res string

	if _ = conn.Query(radix.Cmd(&res, "PING")); res != "PONG" {
		return pingFailed, nil
	}

	return pingOk, nil
}
