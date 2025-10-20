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
	"github.com/mediocregopher/radix/v3"
	"golang.zabbix.com/agent2/plugins/redis/conn"
	"golang.zabbix.com/sdk/plugin/comms"
)

// PingHandler executes 'PING' command and returns pingOk if a connection is alive or pingFailed otherwise.
func PingHandler(redisClient conn.RedisClient, _ map[string]string) (any, error) {
	var res string

	//nolint:errcheck // error is ignored because it will return empty string, which is one of accepted results
	_ = redisClient.Query(radix.Cmd(&res, "PING"))

	if res != "PONG" {
		return comms.PingFailed, nil
	}

	return comms.PingOk, nil
}
