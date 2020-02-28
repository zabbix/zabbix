/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
	"encoding/json"
	"fmt"
	"github.com/mediocregopher/radix/v3"
	"strings"
)

const globChars = "*?[]!"

// configHandler gets an output of 'CONFIG GET [pattern]' command and returns it in JSON format or as a single-value.
func (p *Plugin) configHandler(conn redisClient, params []string) (interface{}, error) {
	var (
		pattern string
		res     map[string]string
	)

	if len(params) > 1 && len(params[1]) > 0 {
		pattern = params[1]
	} else {
		pattern = "*"
	}

	if err := conn.Query(radix.Cmd(&res, "CONFIG", "GET", pattern)); err != nil {
		p.Errf(err.Error())
		return nil, errorCannotFetchData
	}

	if len(res) == 0 {
		return nil, fmt.Errorf("No config parameter found for pattern %q.", pattern)
	}

	if strings.ContainsAny(pattern, globChars) {
		jsonRes, err := json.Marshal(res)
		if err != nil {
			p.Errf(err.Error())
			return nil, errorCannotMarshalJson
		}

		return string(jsonRes), nil
	}

	return res[strings.ToLower(pattern)], nil
}
