/*
** Copyright (C) 2001-2024 Zabbix SIA
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
	"fmt"

	"golang.zabbix.com/sdk/zbxerr"
)

func asmDiskGroupsHandler(ctx context.Context, conn OraClient, params map[string]string,
	_ ...string) (interface{}, error) {
	var diskGroups string

	row, err := conn.QueryRow(ctx, getDiskGRoupQuery(params["Diskgroup"]))
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&diskGroups)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	if diskGroups == "" {
		diskGroups = "[]"
	}

	return diskGroups, nil
}

func getDiskGRoupQuery(name string) string {
	var whereStr string
	if name != "" {
		whereStr = fmt.Sprintf(`WHERE NAME = '%s'`, name)
	}

	return fmt.Sprintf(`
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
		 V$ASM_DISKGROUP
	 %s
`, whereStr)
}
