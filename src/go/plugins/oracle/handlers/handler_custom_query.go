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

package handlers

import (
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"strings"

	"golang.zabbix.com/agent2/plugins/oracle/dbconn"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/zbxerr"
)

// CustomQueryHandler executes custom user queries
//
//goland:noinspection GoUnhandledErrorResult
func CustomQueryHandler(ctx context.Context, conn dbconn.OraClient, //nolint:gocyclo,cyclop
	params map[string]string, extraParams ...string) (any, error) {
	queryName := params["QueryName"]

	queryArgs := make([]any, len(extraParams)) //nolint:makezero
	for i, v := range extraParams {
		queryArgs[i] = v
	}

	rows, err := conn.QueryByName(ctx, queryName, queryArgs...) //nolint:sqlclosecheck
	defer closeRows(rows)

	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	// JSON marshaling
	var data []string

	columns, err := rows.Columns()
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	values := make([]any, len(columns))       //nolint:makezero
	valuePointers := make([]any, len(values)) //nolint:makezero

	for i := range values {
		valuePointers[i] = &values[i]
	}

	results := make(map[string]any)

	for rows.Next() {
		err = rows.Scan(valuePointers...)
		if err != nil {
			if errors.Is(err, sql.ErrNoRows) {
				return nil, errs.WrapConst(err, zbxerr.ErrorEmptyResult)
			}

			return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
		}

		for i, value := range values {
			results[columns[i]] = value
		}

		jsonRes, errMarshal := json.Marshal(results)
		if errMarshal != nil {
			return nil, errs.WrapConst(errMarshal, zbxerr.ErrorCannotMarshalJSON)
		}

		data = append(data, strings.TrimSpace(string(jsonRes)))
	}

	if rows.Err() != nil {
		log.Errf("CustomQueryHandler rows returned error: %v", err)
	}

	return "[" + strings.Join(data, ",") + "]", nil
}

func closeRows(rows *sql.Rows) {
	if rows != nil {
		err := rows.Close()
		if err != nil {
			log.Errf("CustomQueryHandler closing rows raised error: %v", err)
		}
	}
}
