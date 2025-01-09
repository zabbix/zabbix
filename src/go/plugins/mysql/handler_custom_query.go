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

	"golang.zabbix.com/sdk/zbxerr"
)

// customQueryHandler executes custom user queries
func customQueryHandler(ctx context.Context, conn MyClient,
	params map[string]string, extraParams ...string) (interface{}, error) {
	queryName := params["QueryName"]

	queryArgs := make([]interface{}, len(extraParams))
	for i, v := range extraParams {
		queryArgs[i] = v
	}

	rows, err := conn.QueryByName(ctx, queryName, queryArgs...)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}
	defer rows.Close()

	res, err := rows2data(rows)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	jsonRes, err := json.Marshal(res)
	if err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	return string(jsonRes), nil
}
