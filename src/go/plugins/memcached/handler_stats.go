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

package memcached

import (
	"encoding/json"

	"golang.zabbix.com/sdk/zbxerr"
)

const (
	statsTypeGeneral  = ""
	statsTypeItems    = "items"
	statsTypeSizes    = "sizes"
	statsTypeSlabs    = "slabs"
	statsTypeSettings = "settings"
)

// statsHandler gets an output of 'stats <type>' command, parses it and returns it in the JSON format.
func statsHandler(conn MCClient, params map[string]string) (interface{}, error) {
	stats, err := conn.Stats(params["Type"])
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	jsonRes, err := json.Marshal(stats)
	if err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	return string(jsonRes), nil
}
