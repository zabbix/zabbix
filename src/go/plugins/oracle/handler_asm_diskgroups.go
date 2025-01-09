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

func asmDiskGroupsHandler(ctx context.Context, conn OraClient, params map[string]string,
	_ ...string) (interface{}, error) {
	var diskGroups string

	query, args := getDiskGRoupQuery(params["Diskgroup"])

	row, err := conn.QueryRow(ctx, query, args...)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	err = row.Scan(&diskGroups)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	if diskGroups == "" {
		diskGroups = "[]"
	}

	return diskGroups, nil
}

func getDiskGRoupQuery(name string) (string, []any) {
	const query = `
	SELECT
		JSON_ARRAYAGG(
			JSON_OBJECT(NAME VALUE
				JSON_OBJECT(
					'total_bytes' VALUE 
						ROUND(TOTAL_MB / DECODE(TYPE, 'EXTERN', 1, 'NORMAL', 2, 'HIGH', 3) * 1024 * 1024),
					'free_bytes'  VALUE 
						ROUND(USABLE_FILE_MB * 1024 * 1024),
					'used_pct'    VALUE 
						ROUND(100 - (USABLE_FILE_MB / (TOTAL_MB / 
						DECODE(TYPE, 'EXTERN', 1, 'NORMAL', 2, 'HIGH', 3))) * 100, 2)
				)
			) RETURNING CLOB 
		)
	 FROM 
		 V$ASM_DISKGROUP_STAT`

	if name != "" {
		return query + " WHERE NAME = :1", []any{name}
	}

	return query, nil
}
