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

package postgres

import (
	"context"
	"encoding/json"
	"errors"
	"strings"

	"git.zabbix.com/ap/plugin-support/zbxerr"
	"github.com/jackc/pgx/v4"
)

// customQueryHandler executes custom user queries from *.sql files.
func customQueryHandler(ctx context.Context, conn PostgresClient,
	_ string, params map[string]string, extraParams ...string) (interface{}, error) {
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
			if errors.Is(err, pgx.ErrNoRows) {
				return nil, zbxerr.ErrorEmptyResult.Wrap(err)
			}

			return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
		}

		setResult(results, values, columns)

		jsonRes, err := json.Marshal(results)
		if err != nil {
			return nil, err
		}

		data = append(data, strings.TrimSpace(string(jsonRes)))
	}

	// Any errors encountered by rows.Next or rows.Scan will be returned here
	if rows.Err() != nil {
		return nil, err
	}

	return "[" + strings.Join(data, ",") + "]", nil
}

func setResult(results map[string]interface{}, values []interface{}, columns []string) {
	for i, value := range values {
		switch v := value.(type) {
		case []uint8:
			results[columns[i]] = string(v)
		default:
			results[columns[i]] = value
		}
	}
}
