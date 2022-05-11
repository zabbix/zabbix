/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

package oracle

import (
	"context"
	"database/sql"
	"encoding/json"
	"strings"

	"git.zabbix.com/ap/plugin-support/zbxerr"
)

// customQueryHandler executes custom user queries
func customQueryHandler(ctx context.Context, conn OraClient,
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

	// JSON marshaling
	var data []string

	columns, err := rows.Columns()
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	values := make([]interface{}, len(columns))
	valuePointers := make([]interface{}, len(values))

	for i := range values {
		valuePointers[i] = &values[i]
	}

	results := make(map[string]interface{})

	for rows.Next() {
		err = rows.Scan(valuePointers...)
		if err != nil {
			if err == sql.ErrNoRows {
				return nil, zbxerr.ErrorEmptyResult.Wrap(err)
			}

			return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
		}

		for i, value := range values {
			results[columns[i]] = value
		}

		jsonRes, _ := json.Marshal(results)
		data = append(data, strings.TrimSpace(string(jsonRes)))
	}

	return "[" + strings.Join(data, ",") + "]", nil
}
