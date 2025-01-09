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

package redis

import (
	"encoding/json"
	"fmt"
	"strings"

	"github.com/mediocregopher/radix/v3"
	"golang.zabbix.com/sdk/zbxerr"
)

const globChars = "*?[]!"

// configHandler gets an output of 'CONFIG GET [pattern]' command and returns it in JSON format or as a single-value.
func configHandler(conn redisClient, params map[string]string) (interface{}, error) {
	var res map[string]string

	if err := conn.Query(radix.Cmd(&res, "CONFIG", "GET", params["Pattern"])); err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	if len(res) == 0 {
		return nil, fmt.Errorf("no config parameter found for pattern %q", params["Pattern"])
	}

	if strings.ContainsAny(params["Pattern"], globChars) {
		jsonRes, err := json.Marshal(res)
		if err != nil {
			return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
		}

		return string(jsonRes), nil
	}

	return res[strings.ToLower(params["Pattern"])], nil
}
