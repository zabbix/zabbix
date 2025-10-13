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
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

type slowlog []any
type logItem = []any

// getLastSlowlogID gets the last log item ID from slowlog..
func getLastSlowlogID(sl slowlog) (int64, error) {
	if len(sl) == 0 {
		return 0, nil
	}

	item, ok := sl[0].(logItem)
	if !ok {
		return 0, zbxerr.ErrorCannotParseResult
	}

	if len(item) == 0 {
		return 0, zbxerr.ErrorCannotParseResult
	}

	id, ok := item[0].(int64)
	if !ok {
		return 0, zbxerr.ErrorCannotParseResult
	}

	return id + 1, nil
}

// SlowlogHandler gets an output of 'SLOWLOG GET 1' command and returns the last slowlog Id.
func SlowlogHandler(redisClient conn.RedisClient, _ map[string]string) (any, error) {
	var res []any

	err := redisClient.Query(radix.Cmd(&res, "SLOWLOG", "GET", "1"))
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	lastID, err := getLastSlowlogID(res)

	return lastID, err
}
