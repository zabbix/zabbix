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

package memcached

import (
	"encoding/json"
	"strings"
)

// statsHandler gets an output of 'stats <type>' command, parses it and returns it in the JSON format.
func (p *Plugin) statsHandler(conn MCClient, params []string) (interface{}, error) {
	statsType := ""

	if len(params) > 1 {
		switch strings.ToLower(params[1]) {
		case "items":
			fallthrough
		case "sizes":
			fallthrough
		case "slabs":
			fallthrough
		case "settings":
			statsType = strings.ToLower(params[1])
		default:
			return nil, errorInvalidParams
		}
	}

	stats, err := conn.Stats(statsType)
	if err != nil {
		p.Errf(err.Error())
		return nil, errorCannotFetchData
	}

	jsonRes, err := json.Marshal(stats)
	if err != nil {
		p.Errf(err.Error())
		return nil, errorCannotMarshalJSON
	}

	return string(jsonRes), nil
}
