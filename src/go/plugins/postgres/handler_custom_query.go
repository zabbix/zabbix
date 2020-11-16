/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

	"github.com/jackc/pgx/v4"
	"zabbix.com/pkg/zbxerr"
)

const (
	keyPostgresCustom = "pgsql.custom.query"
)

// customQueryHandler executes custom user queries from *.sql files.
func (p *Plugin) customQueryHandler(ctx context.Context, conn PostgresClient, key string, params []string) (interface{}, error) {
	var (
		err  error
		rows pgx.Rows
		data []string
	)
	// for now we are expecting at least one parameter
	if len(params) == 0 {
		return nil, errors.New("The key requires custom query name as fourth parameter")
	}
	if len(params[0]) == 0 {
		return nil, errors.New("Expected custom query name as fourth parameter for the key, got empty string")
	}

	queryName := params[0]
	queryArgs := make([]interface{}, len(params[1:]))

	for i, v := range params[1:] {
		queryArgs[i] = v
	}

	rows, err = conn.QueryByName(ctx, queryName, queryArgs...)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}
	defer rows.Close()

	fields := rows.FieldDescriptions()

	columnNames := make([]string, len(fields))
	for i, fd := range fields {
		columnNames[i] = string(fd.Name)
	}

	values := make([]interface{}, len(columnNames))
	valuePointers := make([]interface{}, len(values))

	for i := range values {
		valuePointers[i] = &values[i]
	}

	results := make(map[string]interface{})

	for rows.Next() {
		err = rows.Scan(valuePointers...)
		if err != nil {
			if err == pgx.ErrNoRows {
				return nil, zbxerr.ErrorEmptyResult.Wrap(err)
			}
			return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
		}

		for i, value := range values {
			results[columnNames[i]] = value
		}

		jsonRes, _ := json.Marshal(results)
		data = append(data, strings.TrimSpace(string(jsonRes)))
	}
	// Any errors encountered by rows.Next or rows.Scan will be returned here
	if rows.Err() != nil {
		return nil, errors.New(formatZabbixError(err.Error()))
	}

	res := "[" + strings.Join(data, ",") + "]"

	return res, nil

}
