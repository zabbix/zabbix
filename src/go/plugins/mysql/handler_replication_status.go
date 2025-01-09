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

package mysql

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"

	"golang.zabbix.com/sdk/zbxerr"
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
