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

	"git.zabbix.com/ap/plugin-support/zbxerr"
)

func pdbHandler(ctx context.Context, conn OraClient, params map[string]string, _ ...string) (interface{}, error) {
	var PDBInfo string

	row, err := conn.QueryRow(ctx, `
   		SELECT
			JSON_ARRAYAGG(
				JSON_OBJECT(NAME VALUE
					JSON_OBJECT(
						'open_mode' VALUE 
							DECODE(OPEN_MODE, 
								'MOUNTED',              1, 
								'READ ONLY',            2, 
								'READ WRITE',           3, 
								'READ ONLY WITH APPLY', 4, 
								'MIGRATE',              5, 
							0)
					)
				)
			)		
		FROM
			V$PDBS
	`)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&PDBInfo)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	if PDBInfo == "" {
		PDBInfo = "[]"
	}

	return PDBInfo, nil
}
