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
	"fmt"
	"strings"
)

const statsMaxParams = 1

const (
	statsTypeGeneral  = ""
	statsTypeItems    = "items"
	statsTypeSizes    = "sizes"
	statsTypeSlabs    = "slabs"
	statsTypeSettings = "settings"
)

// statsHandler gets an output of 'stats <type>' command, parses it and returns it in the JSON format.
func statsHandler(conn MCClient, params []string) (interface{}, error) {
	statsType := statsTypeGeneral

	if len(params) > statsMaxParams {
		return nil, errorTooManyParameters
	}

	if len(params) > 0 {
		switch strings.ToLower(params[0]) {
		case statsTypeItems:
			fallthrough
		case statsTypeSizes:
			fallthrough
		case statsTypeSlabs:
			fallthrough
		case statsTypeSettings:
			fallthrough
		case statsTypeGeneral:
			statsType = strings.ToLower(params[0])

		default:
			return nil, zabbixError{"unknown stats type"}
		}
	}

	stats, err := conn.Stats(statsType)
	if err != nil {
		return nil, fmt.Errorf("%w (%s)", errorCannotFetchData, err.Error())
	}

	jsonRes, err := json.Marshal(stats)
	if err != nil {
		return nil, fmt.Errorf("%w (%s)", errorCannotMarshalJSON, err.Error())
	}

	return string(jsonRes), nil
}
