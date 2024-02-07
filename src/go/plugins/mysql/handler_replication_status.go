/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

package mysql

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"

	"git.zabbix.com/ap/plugin-support/zbxerr"
)

const masterKey = "Master_Host"

func replicationSlaveStatusHandler(
	ctx context.Context, conn MyClient, params map[string]string, _ ...string,
) (any, error) {
	rows, err := conn.Query(ctx, `SHOW SLAVE STATUS`)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	data, err := rows2data(rows)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	if len(data) == 0 {
		return nil, zbxerr.ErrorEmptyResult.Wrap(errors.New("replication is not configured"))
	}

	if params[masterHostParam] != "" {
		for _, m := range data {
			if m[masterKey] == params[masterHostParam] {
				return parseResponse(m)
			}
		}

		return nil, zbxerr.ErrorEmptyResult.Wrap(fmt.Errorf("master host `%s` not found", params[masterHostParam]))
	}

	return parseResponse(data)
}

func parseResponse(data any) (any, error) {
	jsonRes, err := json.Marshal(data)
	if err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	return string(jsonRes), nil
}
