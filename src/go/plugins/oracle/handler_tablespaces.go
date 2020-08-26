/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
	"strings"

	"zabbix.com/pkg/zbxerr"
)

const keyTablespaces = "oracle.ts.stats"

const tablespacesMaxParams = 0

func tablespacesHandler(ctx context.Context, conn OraClient, params []string) (interface{}, error) {
	var tablespaces string

	if len(params) > tablespacesMaxParams {
		return nil, zbxerr.ErrorTooManyParameters
	}

	row, err := conn.QueryRow(ctx, `
		SELECT
			JSON_ARRAYAGG(
				JSON_OBJECT(TABLESPACE_NAME VALUE 
					JSON_OBJECT(
						'contents'   VALUE CONTENTS, 
						'used_bytes' VALUE USED_BYTES, 
						'max_bytes'  VALUE MAX_BYTES, 
						'free_bytes' VALUE FREE_BYTES, 
						'used_pct'   VALUE USED_PCT, 
						'status'     VALUE STATUS 
					) 
				) 
			)
		FROM
			(
			SELECT
				df.TABLESPACE_NAME AS TABLESPACE_NAME, 
				df.CONTENTS AS CONTENTS, 
				NVL(SUM(df.BYTES), 0) AS USED_BYTES, 
				NVL(SUM(df.MAX_BYTES), 0) AS MAX_BYTES, 
				NVL(SUM(f.FREE), 0) AS FREE_BYTES,
				ROUND(DECODE(SUM(df.MAX_BYTES), 0, 0, (SUM(df.BYTES) / SUM(df.MAX_BYTES) * 100)), 2) AS USED_PCT, 
				DECODE(df.STATUS, 'ONLINE', 1, 'OFFLINE', 2, 'READ ONLY', 3, 0) AS STATUS
			FROM
				(
				SELECT
					ddf.FILE_ID, 
					dt.CONTENTS, 
					dt.STATUS, 
					ddf.FILE_NAME, 
					ddf.TABLESPACE_NAME, 
					TRUNC(ddf.BYTES) AS BYTES, 
					TRUNC(GREATEST(ddf.BYTES, ddf.MAXBYTES)) AS MAX_BYTES
				FROM
					DBA_DATA_FILES ddf, 
					DBA_TABLESPACES dt
				WHERE
					ddf.TABLESPACE_NAME = dt.TABLESPACE_NAME 
				) df, 
				(
				SELECT
					TRUNC(SUM(BYTES)) AS FREE, 
					FILE_ID
				FROM
					DBA_FREE_SPACE
				GROUP BY
					FILE_ID 
				) f
			WHERE
				df.FILE_ID = f.FILE_ID (+)
			GROUP BY
				df.TABLESPACE_NAME, df.CONTENTS, df.STATUS
		UNION ALL
			SELECT
				Y.NAME AS TABLESPACE_NAME, 
				Y.CONTENTS AS CONTENTS, 
				NVL(SUM(Y.BYTES), 0) AS BYTES, 
				NVL(SUM(Y.MAX_BYTES), 0) AS MAX_BYTES, 
				NVL(MAX(NVL(Y.FREE_BYTES, 0)), 0) AS FREE,
				ROUND(DECODE(SUM(Y.MAX_BYTES), 0, 0, (SUM(Y.BYTES) / SUM(Y.MAX_BYTES) * 100)), 2) AS USED_PCT, 
				DECODE(Y.TBS_STATUS, 'ONLINE', 1, 'OFFLINE', 2, 'READ ONLY', 3, 0) AS STATUS
			FROM
				(
				SELECT
					dtf.TABLESPACE_NAME AS NAME, 
					dt.CONTENTS, 
					dt.STATUS AS TBS_STATUS, 
					dtf.STATUS AS STATUS, 
					dtf.BYTES AS BYTES, 
					(
					SELECT
						((f.TOTAL_BLOCKS - s.TOT_USED_BLOCKS) * vp.VALUE)
					FROM
						(
						SELECT
							TABLESPACE_NAME, SUM(USED_BLOCKS) TOT_USED_BLOCKS
						FROM
							GV$SORT_SEGMENT
						WHERE
							TABLESPACE_NAME != 'DUMMY'
						GROUP BY
							TABLESPACE_NAME) s, (
						SELECT
							TABLESPACE_NAME, SUM(BLOCKS) TOTAL_BLOCKS
						FROM
							DBA_TEMP_FILES
						WHERE
							TABLESPACE_NAME != 'DUMMY'
						GROUP BY
							TABLESPACE_NAME) f, (
						SELECT
							VALUE
						FROM
							V$PARAMETER
						WHERE
							NAME = 'db_block_size') vp
					WHERE
						f.TABLESPACE_NAME = s.TABLESPACE_NAME
						AND f.TABLESPACE_NAME = dtf.TABLESPACE_NAME 
					) AS FREE_BYTES,
					CASE
						WHEN dtf.MAXBYTES = 0 THEN dtf.BYTES
						ELSE dtf.MAXBYTES
					END AS MAX_BYTES
				FROM
					sys.DBA_TEMP_FILES dtf, 
					sys.DBA_TABLESPACES dt
				WHERE
					dtf.TABLESPACE_NAME = dt.TABLESPACE_NAME ) Y
			GROUP BY
				Y.NAME, Y.CONTENTS, Y.TBS_STATUS
			ORDER BY
				TABLESPACE_NAME 
			)
	`)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&tablespaces)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	// Add leading zeros for floats like ".03".
	// There should be a better way to do that, but I haven't come up with it ¯\_(ツ)_/¯
	tablespaces = strings.ReplaceAll(tablespaces, "\":.", "\":0.")

	return tablespaces, nil
}
