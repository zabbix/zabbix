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

func fraHandler(ctx context.Context, conn OraClient, params map[string]string, _ ...string) (interface{}, error) {
	var FRA string

	row, err := conn.QueryRow(ctx, `
		SELECT
			JSON_OBJECTAGG(v.METRIC VALUE v.VALUE)
		FROM
			(
			SELECT
				METRIC, 
				SUM(VALUE) AS VALUE
			FROM
				(
				SELECT
					'space_limit' AS METRIC, 
					SPACE_LIMIT AS VALUE
				FROM
					V$RECOVERY_FILE_DEST
					
				UNION
				
				SELECT
					'space_used', 
					SPACE_USED AS VALUE
				FROM
					V$RECOVERY_FILE_DEST
					
				UNION
				
				SELECT
					'space_reclaimable', 
					SPACE_RECLAIMABLE AS VALUE
				FROM
					V$RECOVERY_FILE_DEST
					
				UNION
				
				SELECT
					'number_of_files', 
					NUMBER_OF_FILES AS VALUE
				FROM
					V$RECOVERY_FILE_DEST
					
				UNION
				
				SELECT
					'usable_pct', 
					DECODE(SPACE_LIMIT, 0, 0, (100 - (100 * (SPACE_USED - SPACE_RECLAIMABLE) / SPACE_LIMIT))) AS VALUE
				FROM
					V$RECOVERY_FILE_DEST
					
				UNION
				
				SELECT
					'restore_point', 
					COUNT(*) AS VALUE
				FROM
					V$RESTORE_POINT
					
				UNION
				
				SELECT
					DISTINCT *
				FROM
					TABLE(sys.ODCIVARCHAR2LIST('space_limit', 'space_used', 'space_reclaimable', 'number_of_files', 'usable_pct')), 
					TABLE(sys.ODCINUMBERLIST(0, 0, 0, 0, 0)) 
				)
			GROUP BY
				METRIC
			) v
	`)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&FRA)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	return FRA, nil
}
