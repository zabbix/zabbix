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

func cdbHandler(ctx context.Context, conn OraClient, params map[string]string, _ ...string) (interface{}, error) {
	var CDBInfo string

	row, err := conn.QueryRow(ctx, `
   		SELECT
			JSON_ARRAYAGG(
				JSON_OBJECT(NAME VALUE
					JSON_OBJECT(
						'open_mode' 	VALUE 
							DECODE(OPEN_MODE, 
								'MOUNTED',              1, 
								'READ ONLY',            2, 
								'READ WRITE',           3, 
								'READ ONLY WITH APPLY', 4, 
								'MIGRATE', 5, 
							0),
						'role' 			VALUE 
							DECODE(DATABASE_ROLE,
								'SNAPSHOT STANDBY', 1, 
								'LOGICAL STANDBY',  2, 
								'PHYSICAL STANDBY', 3, 
								'PRIMARY',          4, 
								'FAR SYNC', 5, 
							0),
						'force_logging' VALUE 
							DECODE(FORCE_LOGGING, 
								'YES', 1, 
								'NO' , 0, 
							0),
						'log_mode'      VALUE 
							DECODE(LOG_MODE, 
								'NOARCHIVELOG', 0,
								'ARCHIVELOG', 1, 
 								'MANUAL', 2,
							0)
					)
				)
			)		
		FROM
			V$DATABASE
	`)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&CDBInfo)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	if CDBInfo == "" {
		CDBInfo = "[]"
	}

	return CDBInfo, nil
}
