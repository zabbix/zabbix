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
	"context"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

func pdbHandler(ctx context.Context, conn OraClient, params map[string]string, _ ...string) (interface{}, error) {
	var PDBInfo string

	query, args := getPDBQuery(params["Database"])

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

func getPDBQuery(name string) (string, []any) {
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
		return query + " WHERE NAME = :1", []any{name}
	}

	return query, nil
}
