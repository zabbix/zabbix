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
	"database/sql"

	"golang.zabbix.com/sdk/errs"
)

var errRetrieveDataFromRowsFailed = errs.New("cannot unmarshal JSON")

// rows2data scans rows and returns it as an array of key-value pairs.
// https://github.com/go-sql-driver/mysql/wiki/Examples
func rows2data(rows *sql.Rows) ([]map[string]string, error) {
	defer rows.Close() //nolint:errcheck

	columns, err := rows.Columns()
	if err != nil {
		return nil, errs.WrapConst(err, errRetrieveDataFromRowsFailed)
	}

	values := make([]sql.RawBytes, len(columns)) //nolint:makezero //columns len usually > 0

	// rows.Scan wants '[]interface{}' as an argument, so we must copy the
	// references into such a slice
	// See http://code.google.com/p/go-wiki/wiki/InterfaceSlice for details
	scanArgs := make([]any, len(values)) //nolint:makezero
	for i := range values {
		scanArgs[i] = &values[i]
	}

	var result []map[string]string

	for rows.Next() {
		err = rows.Scan(scanArgs...)
		if err != nil {
			return nil, errs.WrapConst(err, errRetrieveDataFromRowsFailed)
		}

		entry := make(map[string]string)

		for i, col := range values {
			if col == nil {
				entry[columns[i]] = ""
			} else {
				entry[columns[i]] = string(col)
			}
		}

		result = append(result, entry)
	}

	return result, nil
}
