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

package oracle

import (
	"fmt"
	"strings"
	"context"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

func pdbHandler(ctx context.Context, conn OraClient, params map[string]string, _ ...string) (interface{}, error) {
	var PDBInfo string

	connname := params["Database"]

	// Check if first character is numeric
	var conntype string
	if connname != "" && strings.ToUpper(connname)[0] < 65 {
		conntype = "CON_ID"
	} else {
		conntype = "NAME"
	}

	query, args := getPDBQuery(conntype, connname)

	row, err := conn.QueryRow(ctx, query, args...)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)

	}

	err = row.Scan(&PDBInfo)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	if PDBInfo == "" {
		PDBInfo = "[]"
	}

	return PDBInfo, nil
}

func getPDBQuery(conntype, name string) (string, []any) {
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
		return fmt.Sprintf(`%s WHERE %s = :1`, query, conntype), []any{name}
	}

	return query, nil
}
