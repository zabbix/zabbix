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

	"golang.zabbix.com/agent2/plugins/oracle/dbconn"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

// SgaHandler function works with System Global Area (SGA) statistics.
func SgaHandler(ctx context.Context, conn dbconn.OraClient, _ map[string]string, _ ...string) (any, error) {
	var sga string

	//nolint:lll
	row, err := conn.QueryRow(ctx, `
		SELECT
			JSON_OBJECTAGG(v.POOL VALUE v.BYTES)
		FROM
			(
			SELECT
				POOL, 
				SUM(BYTES) AS BYTES
			FROM
				(
				SELECT
					LOWER(REPLACE(POOL, ' ', '_')) AS POOL,
					SUM(BYTES) AS BYTES
				FROM
					V$SGASTAT
				WHERE
					POOL IN ('java pool', 'large pool')
				GROUP BY
					POOL
					
				UNION
				
				SELECT
					'shared_pool',
					SUM(BYTES)
				FROM
					V$SGASTAT
				WHERE
					POOL = 'shared pool'
					AND NAME NOT IN ('library cache', 'dictionary cache', 'free memory', 'sql area')
					
				UNION
				
				SELECT
					NAME,
					BYTES
				FROM
					V$SGASTAT
				WHERE
					POOL IS NULL
					AND NAME IN ('log_buffer', 'fixed_sga')
					
				UNION
				
				SELECT
					'buffer_cache',
					SUM(BYTES)
				FROM
					V$SGASTAT
				WHERE
					POOL IS NULL
					AND NAME IN ('buffer_cache', 'db_block_buffers')
					
				UNION
				
				SELECT
					DISTINCT *
				FROM
					TABLE(sys.ODCIVARCHAR2LIST('buffer_cache', 'fixed_sga', 'java_pool', 'large_pool', 'log_buffer', 'shared_pool')), 
					TABLE(sys.ODCINUMBERLIST(0, 0, 0, 0, 0, 0))	
				)
			GROUP BY
				POOL
			) v
	`)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	err = row.Scan(&sga)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	return sga, nil
}
