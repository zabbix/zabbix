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
	"fmt"
	"strings"

	"golang.zabbix.com/agent2/plugins/oracle/dbconn"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

// PdbHandler function works with Pluggable Databases (PDBs) information.
func PdbHandler(ctx context.Context, conn dbconn.OraClient, params map[string]string, _ ...string) (any, error) {
	var pdbInfo string

	connName := params["Database"]

	// Check if the first character is numeric
	var connType string
	if connName != "" && strings.ToUpper(connName)[0] < 65 {
		connType = "CON_ID"
	} else {
		connType = "NAME"
	}

	query, args := getPDBQuery(connType, connName)

	row, err := conn.QueryRow(ctx, query, args...)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	err = row.Scan(&pdbInfo)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	if pdbInfo == "" {
		pdbInfo = "[]"
	}

	return pdbInfo, nil
}

func getPDBQuery(connType, name string) (string, []any) {
	const query = `
	SELECT
		JSON_ARRAYAGG(
			JSON_OBJECT(
				NAME VALUE JSON_OBJECT(
					'open_mode' VALUE DECODE(
						OPEN_MODE,
						'MOUNTED',
						1,
						'READ ONLY',
						2,
						'READ WRITE',
						3,
						'READ ONLY WITH APPLY',
						4,
						'MIGRATE',
						5,
						0
					)
				)
			)
		)
		FROM
			V$PDBS`

	if name != "" {
		return fmt.Sprintf(`%s WHERE %s = :1`, query, connType), []any{name}
	}

	return query, nil
}
