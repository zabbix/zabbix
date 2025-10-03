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

const (
	temp          = "TEMPORARY"
	perm          = "PERMANENT"
	undo          = "UNDO"
	defaultPERMTS = "USERS"
	defaultTEMPTS = "TEMP"
)

// TablespacesHandler function works with tablespace statistics.
func TablespacesHandler(ctx context.Context, conn dbconn.OraClient, params map[string]string,
	_ ...string) (any, error) {
	var tableSpaces string

	query, args, err := getTablespacesQuery(params)
	if err != nil {
		return nil, errs.Wrap(err, "failed to retrieve tablespace query")
	}

	row, err := conn.QueryRow(ctx, query, args...)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	err = row.Scan(&tableSpaces)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	// Add leading zeros for floats: ".03" -> "0.03".
	// Oracle JSON functions are not RFC 4627 compliant.
	// There should be a better way to do that, but I haven't come up with it ¯\_(ツ)_/¯
	tableSpaces = strings.ReplaceAll(tableSpaces, "\":.", "\":0.")

	return tableSpaces, nil
}

//nolint:gocyclo,cyclop
func getTablespacesQuery(params map[string]string) (string, []any, error) {
	ts := params["Tablespace"]
	t := params["Type"]
	connName := params["Conname"]

	// Check if the first character is numeric
	var connType string
	if connName != "" && strings.ToUpper(connName)[0] < 65 {
		connType = "CON_ID"
	} else {
		connType = "CON$NAME"
	}

	if ts == "" && t == "" {
		if connName == "" {
			return getFullQuery(), nil, nil
		}

		return getFullQueryConn(connType), []any{connName, connName}, nil
	}

	var err error

	ts, t, err = prepValues(ts, t)
	if err != nil {
		return "", nil, errs.WrapConst(err, zbxerr.ErrorInvalidParams)
	}

	switch t {
	case perm, undo:
		if connName != "" {
			return getPermQueryConnPart(connType), []any{connName, ts}, nil
		}

		return getPermQueryPart(), []any{ts}, nil
	case temp:
		if connName != "" {
			return getTempQueryConnPart(connType), []any{connName, ts}, nil
		}

		return getTempQueryPart(), []any{ts}, nil
	default:
		return "", nil, errs.Wrapf(zbxerr.ErrorInvalidParams, "incorrect table-space type %s", t)
	}
}

func prepValues(tableSpace, tableType string) (string, string, error) {
	if tableSpace != "" && tableType != "" {
		return tableSpace, tableType, nil
	}

	if tableSpace == "" {
		if tableType == perm {
			return defaultPERMTS, tableType, nil
		}

		if tableType == temp {
			return defaultTEMPTS, tableType, nil
		}

		return "", "", errs.Errorf("incorrect type %s", tableType)
	}

	return tableSpace, perm, nil
}

func getFullQuery() string {
	return `
SELECT JSON_ARRAYAGG(
               JSON_OBJECT(CON_NAME VALUE
                           JSON_ARRAYAGG(
                                   JSON_OBJECT(TABLESPACE_NAME VALUE
                                               JSON_OBJECT(
                                                       'contents' VALUE CONTENTS,
                                                       'file_bytes' VALUE FILE_BYTES,
                                                       'max_bytes' VALUE MAX_BYTES,
                                                       'free_bytes' VALUE FREE_BYTES,
                                                       'used_bytes' VALUE USED_BYTES,
                                                       'used_pct_max' VALUE USED_PCT_MAX,
                                                       'used_file_pct' VALUE USED_FILE_PCT,
                                                       'used_from_max_pct' VALUE USED_FROM_MAX_PCT,
                                                       'status' VALUE STATUS
                                                   )
                                       )
                               )
                   ) RETURNING CLOB
           )
FROM (SELECT NVL(df.CON_NAME, 'NOCDB')           AS CON_NAME,
             df.TABLESPACE_NAME                  AS TABLESPACE_NAME,
             df.CONTENTS                         AS CONTENTS,
             NVL(SUM(df.BYTES), 0)               AS FILE_BYTES,
             NVL(SUM(df.MAX_BYTES), 0)           AS MAX_BYTES,
             NVL(SUM(f.FREE), 0)                 AS FREE_BYTES,
             NVL(SUM(df.BYTES) - SUM(f.FREE), 0) AS USED_BYTES,
             ROUND(CASE SUM(df.MAX_BYTES)
                       WHEN 0 THEN 0
                       ELSE (SUM(df.BYTES) / SUM(df.MAX_BYTES) * 100)
                       END, 2)                   AS USED_PCT_MAX,
             ROUND(CASE SUM(df.BYTES)
                       WHEN 0 THEN 0
                       ELSE (NVL(SUM(df.BYTES) - SUM(f.FREE), 0)) / SUM(df.BYTES) * 100
                       END, 2)                   AS USED_FILE_PCT,
             ROUND(CASE SUM(df.MAX_BYTES)
                       WHEN 0 THEN 0
                       ELSE NVL(SUM(df.BYTES) - SUM(f.FREE), 0) / SUM(df.MAX_BYTES) * 100
                       END, 2)                   AS USED_FROM_MAX_PCT,
             CASE df.STATUS
                 WHEN 'ONLINE' THEN 1
                 WHEN 'OFFLINE' THEN 2
                 WHEN 'READ ONLY' THEN 3
                 ELSE 0
                 END                             AS STATUS
      FROM (SELECT ct.CON$NAME                              AS CON_NAME,
                   cdf.FILE_ID,
                   ct.CONTENTS,
                   ct.STATUS,
                   cdf.FILE_NAME,
                   cdf.TABLESPACE_NAME,
                   TRUNC(cdf.BYTES)                         AS BYTES,
                   TRUNC(GREATEST(cdf.BYTES, cdf.MAXBYTES)) AS MAX_BYTES
            FROM CDB_DATA_FILES cdf,
                 CDB_TABLESPACES ct
            WHERE cdf.TABLESPACE_NAME = ct.TABLESPACE_NAME
              AND cdf.CON_ID = ct.CON_ID) df,
           (SELECT TRUNC(SUM(BYTES)) AS FREE,
                   FILE_ID
            FROM CDB_FREE_SPACE
            GROUP BY FILE_ID) f
      WHERE df.FILE_ID = f.FILE_ID (+)
      GROUP BY df.CON_NAME,
               df.TABLESPACE_NAME,
               df.CONTENTS,
               df.STATUS
      UNION ALL
      SELECT NVL(Y.CON_NAME, 'NOCDB')                 AS CON_NAME,
             Y.NAME                                   AS TABLESPACE_NAME,
             Y.CONTENTS                               AS CONTENTS,
             NVL(SUM(Y.BYTES), 0)                     AS FILE_BYTES,
             NVL(SUM(Y.MAX_BYTES), 0)                 AS MAX_BYTES,
             NVL(MAX(NVL(Y.FREE_BYTES, 0)), 0)        AS FREE_BYTES,
             NVL(SUM(Y.BYTES) - MAX(Y.FREE_BYTES), 0) AS USED_BYTES,
             ROUND(CASE SUM(Y.MAX_BYTES)
                       WHEN 0 THEN 0
                       ELSE (SUM(Y.BYTES) / SUM(Y.MAX_BYTES) * 100)
                       END, 2)                        AS USED_PCT_MAX,
             ROUND(CASE SUM(Y.BYTES)
                       WHEN 0 THEN 0
                       ELSE (NVL(SUM(Y.BYTES) - MAX(Y.FREE_BYTES), 0)) / SUM(Y.BYTES) * 100
                       END, 2)                        AS USED_FILE_PCT,
             ROUND(CASE SUM(Y.MAX_BYTES)
                       WHEN 0 THEN 0
                       ELSE NVL(SUM(Y.BYTES) - MAX(Y.FREE_BYTES), 0) / SUM(Y.MAX_BYTES) * 100
                       END, 2)                        AS USED_FROM_MAX_PCT,
             CASE Y.STATUS
                 WHEN 'ONLINE' THEN 1
                 WHEN 'OFFLINE' THEN 2
                 WHEN 'READ ONLY' THEN 3
                 ELSE 0
                 END                                  AS STATUS
      FROM (SELECT ct.CON$NAME                  AS CON_NAME,
                   ctf.TABLESPACE_NAME          AS NAME,
                   ct.CONTENTS,
                   ctf.STATUS                   AS STATUS,
                   ctf.BYTES                    AS BYTES,
                   (SELECT ((f.TOTAL_BLOCKS - s.TOT_USED_BLOCKS) * vp.VALUE)
                    FROM (SELECT CON_ID,
                                 TABLESPACE_NAME,
                                 SUM(USED_BLOCKS) TOT_USED_BLOCKS
                          FROM V$SORT_SEGMENT
                          WHERE TABLESPACE_NAME != 'DUMMY'
                          GROUP BY CON_ID,
                                   TABLESPACE_NAME) s,
                         (SELECT CON_ID,
                                 TABLESPACE_NAME,
                                 SUM(BLOCKS) TOTAL_BLOCKS
                          FROM CDB_TEMP_FILES
                          WHERE TABLESPACE_NAME != 'DUMMY'
                          GROUP BY CON_ID,
                                   TABLESPACE_NAME) f,
                         (SELECT VALUE
                          FROM V$PARAMETER
                          WHERE NAME = 'db_block_size') vp
                    WHERE f.TABLESPACE_NAME = s.TABLESPACE_NAME
                      AND f.TABLESPACE_NAME = ctf.TABLESPACE_NAME
                      AND f.CON_ID = s.CON_ID
                      AND f.CON_ID = ct.CON_ID) AS FREE_BYTES,
                   CASE
                       WHEN ctf.MAXBYTES = 0 THEN ctf.BYTES
                       ELSE ctf.MAXBYTES
                       END                      AS MAX_BYTES
            FROM CDB_TEMP_FILES ctf,
                 CDB_TABLESPACES ct
            WHERE ctf.TABLESPACE_NAME = ct.TABLESPACE_NAME
              AND ctf.CON_ID = ct.CON_ID) Y
      GROUP BY Y.CON_NAME,
               Y.NAME,
               Y.CONTENTS,
               Y.STATUS)
GROUP BY CON_NAME`
}

func getFullQueryConn(connType string) string {
	return fmt.Sprintf(`
SELECT JSON_ARRAYAGG(
               JSON_OBJECT(TABLESPACE_NAME VALUE
                           JSON_OBJECT(
                                   'contents' VALUE CONTENTS,
                                   'file_bytes' VALUE FILE_BYTES,
                                   'max_bytes' VALUE MAX_BYTES,
                                   'free_bytes' VALUE FREE_BYTES,
                                   'used_bytes' VALUE USED_BYTES,
                                   'used_pct_max' VALUE USED_PCT_MAX,
                                   'used_file_pct' VALUE USED_FILE_PCT,
                                   'used_from_max_pct' VALUE USED_FROM_MAX_PCT,
                                   'status' VALUE STATUS
                               )
                   ) RETURNING CLOB
           )
FROM (SELECT df.TABLESPACE_NAME                  AS TABLESPACE_NAME,
             df.CONTENTS                         AS CONTENTS,
             NVL(SUM(df.BYTES), 0)               AS FILE_BYTES,
             NVL(SUM(df.MAX_BYTES), 0)           AS MAX_BYTES,
             NVL(SUM(f.FREE), 0)                 AS FREE_BYTES,
             NVL(SUM(df.BYTES) - SUM(f.FREE), 0) AS USED_BYTES,
             ROUND(CASE SUM(df.MAX_BYTES)
                       WHEN 0 THEN 0
                       ELSE (SUM(df.BYTES) / SUM(df.MAX_BYTES) * 100)
                       END, 2)                   AS USED_PCT_MAX,
             ROUND(CASE SUM(df.BYTES)
                       WHEN 0 THEN 0
                       ELSE (NVL(SUM(df.BYTES) - SUM(f.FREE), 0)) / SUM(df.BYTES) * 100
                       END, 2)                   AS USED_FILE_PCT,
             ROUND(CASE SUM(df.MAX_BYTES)
                       WHEN 0 THEN 0
                       ELSE NVL(SUM(df.BYTES) - SUM(f.FREE), 0) / SUM(df.MAX_BYTES) * 100
                       END, 2)                   AS USED_FROM_MAX_PCT,
             CASE df.STATUS
                 WHEN 'ONLINE' THEN 1
                 WHEN 'OFFLINE' THEN 2
                 WHEN 'READ ONLY' THEN 3
                 ELSE 0
                 END                             AS STATUS
      FROM (SELECT ct.CON$NAME                              AS CON_NAME,
                   cdf.FILE_ID,
                   ct.CONTENTS,
                   ct.STATUS,
                   cdf.FILE_NAME,
                   cdf.TABLESPACE_NAME,
                   TRUNC(cdf.BYTES)                         AS BYTES,
                   TRUNC(GREATEST(cdf.BYTES, cdf.MAXBYTES)) AS MAX_BYTES
            FROM CDB_DATA_FILES cdf,
                 CDB_TABLESPACES ct
            WHERE cdf.TABLESPACE_NAME = ct.TABLESPACE_NAME
              AND cdf.CON_ID = ct.CON_ID
              AND (ct.%s = :1 or (ct.CON$NAME is null and ct.CON_ID = 0))) df,
           (SELECT TRUNC(SUM(BYTES)) AS FREE,
                   FILE_ID
            FROM CDB_FREE_SPACE
            GROUP BY FILE_ID) f
      WHERE df.FILE_ID = f.FILE_ID (+)
      GROUP BY df.CON_NAME,
               df.TABLESPACE_NAME,
               df.CONTENTS,
               df.STATUS
      UNION ALL
      SELECT Y.NAME                                   AS TABLESPACE_NAME,
             Y.CONTENTS                               AS CONTENTS,
             NVL(SUM(Y.BYTES), 0)                     AS FILE_BYTES,
             NVL(SUM(Y.MAX_BYTES), 0)                 AS MAX_BYTES,
             NVL(MAX(NVL(Y.FREE_BYTES, 0)), 0)        AS FREE_BYTES,
             NVL(SUM(Y.BYTES) - SUM(Y.FREE_BYTES), 0) AS USED_BYTES,
             ROUND(CASE SUM(Y.MAX_BYTES)
                       WHEN 0 THEN 0
                       ELSE (SUM(Y.BYTES) / SUM(Y.MAX_BYTES) * 100)
                       END, 2)                        AS USED_PCT_MAX,
             ROUND(CASE SUM(Y.BYTES)
                       WHEN 0 THEN 0
                       ELSE (NVL(SUM(Y.BYTES) - SUM(Y.FREE_BYTES), 0)) / SUM(Y.BYTES) * 100
                       END, 2)                        AS USED_FILE_PCT,
             ROUND(CASE SUM(Y.MAX_BYTES)
                       WHEN 0 THEN 0
                       ELSE NVL(SUM(Y.BYTES) - MAX(Y.FREE_BYTES), 0) / SUM(Y.MAX_BYTES) * 100
                       END, 2)                        AS USED_FROM_MAX_PCT,
             CASE Y.STATUS
                 WHEN 'ONLINE' THEN 1
                 WHEN 'OFFLINE' THEN 2
                 WHEN 'READ ONLY' THEN 3
                 ELSE 0
                 END                                  AS STATUS
      FROM (SELECT ct.CON$NAME                  AS CON_NAME,
                   ctf.TABLESPACE_NAME          AS NAME,
                   ct.CONTENTS,
                   ctf.STATUS                   AS STATUS,
                   ctf.BYTES                    AS BYTES,
                   (SELECT ((f.TOTAL_BLOCKS - s.TOT_USED_BLOCKS) * vp.VALUE)
                    FROM (SELECT CON_ID,
                                 TABLESPACE_NAME,
                                 SUM(USED_BLOCKS) TOT_USED_BLOCKS
                          FROM V$SORT_SEGMENT
                          WHERE TABLESPACE_NAME != 'DUMMY'
                          GROUP BY CON_ID,
                                   TABLESPACE_NAME) s,
                         (SELECT CON_ID,
                                 TABLESPACE_NAME,
                                 SUM(BLOCKS) TOTAL_BLOCKS
                          FROM CDB_TEMP_FILES
                          WHERE TABLESPACE_NAME != 'DUMMY'
                          GROUP BY CON_ID,
                                   TABLESPACE_NAME) f,
                         (SELECT VALUE
                          FROM V$PARAMETER
                          WHERE NAME = 'db_block_size') vp
                    WHERE f.TABLESPACE_NAME = s.TABLESPACE_NAME
                      AND f.TABLESPACE_NAME = ctf.TABLESPACE_NAME
                      AND f.CON_ID = s.CON_ID
                      AND f.CON_ID = ct.CON_ID) AS FREE_BYTES,
                   CASE
                       WHEN ctf.MAXBYTES = 0 THEN ctf.BYTES
                       ELSE ctf.MAXBYTES
                       END                      AS MAX_BYTES
            FROM CDB_TEMP_FILES ctf,
                 CDB_TABLESPACES ct
            WHERE ctf.TABLESPACE_NAME = ct.TABLESPACE_NAME
              AND ctf.CON_ID = ct.CON_ID
              AND (ct.%s = :2 or (ct.CON$NAME is null and ct.CON_ID = 0))) Y
      GROUP BY Y.CON_NAME,
               Y.NAME,
               Y.CONTENTS,
               Y.STATUS)`, connType, connType)
}

func getPermQueryPart() string {
	return `
SELECT JSON_ARRAYAGG(
               JSON_OBJECT(CON_NAME VALUE
                           JSON_ARRAYAGG(
                                   JSON_OBJECT(
                                           TABLESPACE_NAME VALUE JSON_OBJECT(
                                           'contents' VALUE CONTENTS,
                                           'file_bytes' VALUE FILE_BYTES,
                                           'max_bytes' VALUE MAX_BYTES,
                                           'free_bytes' VALUE FREE_BYTES,
                                           'used_bytes' VALUE USED_BYTES,
                                           'used_pct_max' VALUE USED_PCT_MAX,
                                           'used_file_pct' VALUE USED_FILE_PCT,
                                           'used_from_max_pct' VALUE USED_FROM_MAX_PCT,
                                           'status' VALUE STATUS
                                       )
                                       )
                               )
                   ) RETURNING CLOB
           )
FROM (SELECT NVL(df.CON_NAME, 'NOCDB')           AS CON_NAME,
             df.TABLESPACE_NAME                  AS TABLESPACE_NAME,
             df.CONTENTS                         AS CONTENTS,
             NVL(SUM(df.BYTES), 0)               AS FILE_BYTES,
             NVL(SUM(df.MAX_BYTES), 0)           AS MAX_BYTES,
             NVL(SUM(f.FREE), 0)                 AS FREE_BYTES,
             NVL(SUM(df.BYTES) - SUM(f.FREE), 0) AS USED_BYTES,
             ROUND(CASE SUM(df.MAX_BYTES)
                       WHEN 0 THEN 0
                       ELSE (SUM(df.BYTES) / SUM(df.MAX_BYTES) * 100)
                       END, 2)                   AS USED_PCT_MAX,
             ROUND(CASE SUM(df.BYTES)
                       WHEN 0 THEN 0
                       ELSE (NVL(SUM(df.BYTES) - SUM(f.FREE), 0)) / SUM(df.BYTES) * 100
                       END, 2)                   AS USED_FILE_PCT,
             ROUND(CASE SUM(df.MAX_BYTES)
                       WHEN 0 THEN 0
                       ELSE NVL(SUM(df.BYTES) - SUM(f.FREE), 0) / SUM(df.MAX_BYTES) * 100
                       END, 2)                   AS USED_FROM_MAX_PCT,
             CASE df.STATUS
                 WHEN 'ONLINE' THEN 1
                 WHEN 'OFFLINE' THEN 2
                 WHEN 'READ ONLY' THEN 3
                 ELSE 0
                 END                             AS STATUS
      FROM (SELECT ct.CON$NAME                              AS CON_NAME,
                   cdf.FILE_ID,
                   ct.CONTENTS,
                   ct.STATUS,
                   cdf.FILE_NAME,
                   cdf.TABLESPACE_NAME,
                   TRUNC(cdf.BYTES)                         AS BYTES,
                   TRUNC(GREATEST(cdf.BYTES, cdf.MAXBYTES)) AS MAX_BYTES
            FROM CDB_DATA_FILES cdf,
                 CDB_TABLESPACES ct
            WHERE cdf.TABLESPACE_NAME = ct.TABLESPACE_NAME
              AND cdf.CON_ID = ct.CON_ID) df,
           (SELECT TRUNC(SUM(BYTES)) AS FREE,
                   FILE_ID
            FROM CDB_FREE_SPACE
            GROUP BY FILE_ID) f
      WHERE df.FILE_ID = f.FILE_ID (+)
        AND df.TABLESPACE_NAME = :1
      GROUP BY df.CON_NAME,
               df.TABLESPACE_NAME,
               df.CONTENTS,
               df.STATUS)
GROUP BY CON_NAME`
}

func getTempQueryPart() string {
	return `
SELECT JSON_ARRAYAGG(
               JSON_OBJECT(CON_NAME VALUE
                           JSON_ARRAYAGG(
                                   JSON_OBJECT(
                                           TABLESPACE_NAME VALUE JSON_OBJECT(
                                           'contents' VALUE CONTENTS,
                                           'file_bytes' VALUE FILE_BYTES,
                                           'max_bytes' VALUE MAX_BYTES,
                                           'free_bytes' VALUE FREE_BYTES,
                                           'used_bytes' VALUE USED_BYTES,
                                           'used_pct_max' VALUE USED_PCT_MAX,
                                           'used_file_pct' VALUE USED_FILE_PCT,
                                           'used_from_max_pct' VALUE USED_FROM_MAX_PCT,
                                           'status' VALUE STATUS
                                       )
                                       )
                               )
                   ) RETURNING CLOB
           )
FROM (SELECT NVL(Y.CON_NAME, 'NOCDB')                 AS CON_NAME,
             Y.NAME                                   AS TABLESPACE_NAME,
             Y.CONTENTS                               AS CONTENTS,
             NVL(SUM(Y.BYTES), 0)                     AS FILE_BYTES,
             NVL(SUM(Y.MAX_BYTES), 0)                 AS MAX_BYTES,
             NVL(MAX(NVL(Y.FREE_BYTES, 0)), 0)        AS FREE_BYTES,
             NVL(SUM(Y.BYTES) - SUM(Y.FREE_BYTES), 0) AS USED_BYTES,
             ROUND(CASE SUM(Y.MAX_BYTES)
                       WHEN 0 THEN 0
                       ELSE (SUM(Y.BYTES) / SUM(Y.MAX_BYTES) * 100)
                       END, 2)                        AS USED_PCT_MAX,
             ROUND(CASE SUM(Y.BYTES)
                       WHEN 0 THEN 0
                       ELSE (NVL(SUM(Y.BYTES) - SUM(Y.FREE_BYTES), 0)) / SUM(Y.BYTES) * 100
                       END, 2)                        AS USED_FILE_PCT,
             ROUND(CASE SUM(Y.MAX_BYTES)
                       WHEN 0 THEN 0
                       ELSE NVL(SUM(Y.BYTES) - MAX(Y.FREE_BYTES), 0) / SUM(Y.MAX_BYTES) * 100
                       END, 2)                        AS USED_FROM_MAX_PCT,
             CASE Y.STATUS
                 WHEN 'ONLINE' THEN 1
                 WHEN 'OFFLINE' THEN 2
                 WHEN 'READ ONLY' THEN 3
                 ELSE 0
                 END                                  AS STATUS
      FROM (SELECT ct.CON$NAME                  AS CON_NAME,
                   ctf.TABLESPACE_NAME          AS NAME,
                   ct.CONTENTS,
                   ctf.STATUS                   AS STATUS,
                   ctf.BYTES                    AS BYTES,
                   (SELECT ((f.TOTAL_BLOCKS - s.TOT_USED_BLOCKS) * vp.VALUE)
                    FROM (SELECT CON_ID,
                                 TABLESPACE_NAME,
                                 SUM(USED_BLOCKS) TOT_USED_BLOCKS
                          FROM V$SORT_SEGMENT
                          WHERE TABLESPACE_NAME != 'DUMMY'
                          GROUP BY CON_ID,
                                   TABLESPACE_NAME) s,
                         (SELECT CON_ID,
                                 TABLESPACE_NAME,
                                 SUM(BLOCKS) TOTAL_BLOCKS
                          FROM CDB_TEMP_FILES
                          WHERE TABLESPACE_NAME != 'DUMMY'
                          GROUP BY CON_ID,
                                   TABLESPACE_NAME) f,
                         (SELECT VALUE
                          FROM V$PARAMETER
                          WHERE NAME = 'db_block_size') vp
                    WHERE f.TABLESPACE_NAME = s.TABLESPACE_NAME
                      AND f.TABLESPACE_NAME = ctf.TABLESPACE_NAME
                      AND f.CON_ID = s.CON_ID
                      AND f.CON_ID = ct.CON_ID) AS FREE_BYTES,
                   CASE
                       WHEN ctf.MAXBYTES = 0 THEN ctf.BYTES
                       ELSE ctf.MAXBYTES
                       END                      AS MAX_BYTES
            FROM CDB_TEMP_FILES ctf,
                 CDB_TABLESPACES ct
            WHERE ctf.TABLESPACE_NAME = ct.TABLESPACE_NAME
              AND ctf.CON_ID = ct.CON_ID
              AND ctf.TABLESPACE_NAME = :1) Y
      GROUP BY Y.CON_NAME, Y.NAME, Y.CONTENTS, Y.STATUS)
GROUP BY CON_NAME`
}

func getPermQueryConnPart(connType string) string {
	return fmt.Sprintf(`
SELECT JSON_ARRAYAGG(
               JSON_OBJECT(
                       TABLESPACE_NAME VALUE JSON_OBJECT(
                       'contents' VALUE CONTENTS,
                       'file_bytes' VALUE FILE_BYTES,
                       'max_bytes' VALUE MAX_BYTES,
                       'free_bytes' VALUE FREE_BYTES,
                       'used_bytes' VALUE USED_BYTES,
                       'used_pct_max' VALUE USED_PCT_MAX,
                       'used_file_pct' VALUE USED_FILE_PCT,
                       'used_from_max_pct' VALUE USED_FROM_MAX_PCT,
                       'status' VALUE STATUS
                   )
                   ) RETURNING CLOB
           )
FROM (SELECT df.TABLESPACE_NAME                  AS TABLESPACE_NAME,
             df.CONTENTS                         AS CONTENTS,
             NVL(SUM(df.BYTES), 0)               AS FILE_BYTES,
             NVL(SUM(df.MAX_BYTES), 0)           AS MAX_BYTES,
             NVL(SUM(f.FREE), 0)                 AS FREE_BYTES,
             NVL(SUM(df.BYTES) - SUM(f.FREE), 0) AS USED_BYTES,
             ROUND(CASE SUM(df.MAX_BYTES)
                       WHEN 0 THEN 0
                       ELSE (SUM(df.BYTES) / SUM(df.MAX_BYTES) * 100)
                       END, 2)                   AS USED_PCT_MAX,
             ROUND(CASE SUM(df.BYTES)
                       WHEN 0 THEN 0
                       ELSE (NVL(SUM(df.BYTES) - SUM(f.FREE), 0)) / SUM(df.BYTES) * 100
                       END, 2)                   AS USED_FILE_PCT,
             ROUND(CASE SUM(df.MAX_BYTES)
                       WHEN 0 THEN 0
                       ELSE NVL(SUM(df.BYTES) - SUM(f.FREE), 0) / SUM(df.MAX_BYTES) * 100
                       END, 2)                   AS USED_FROM_MAX_PCT,
             CASE df.STATUS
                 WHEN 'ONLINE' THEN 1
                 WHEN 'OFFLINE' THEN 2
                 WHEN 'READ ONLY' THEN 3
                 ELSE 0
                 END                             AS STATUS
      FROM (SELECT ct.CON$NAME                              AS CON_NAME,
                   cdf.FILE_ID,
                   ct.CONTENTS,
                   ct.STATUS,
                   cdf.FILE_NAME,
                   cdf.TABLESPACE_NAME,
                   TRUNC(cdf.BYTES)                         AS BYTES,
                   TRUNC(GREATEST(cdf.BYTES, cdf.MAXBYTES)) AS MAX_BYTES
            FROM CDB_DATA_FILES cdf,
                 CDB_TABLESPACES ct
            WHERE cdf.TABLESPACE_NAME = ct.TABLESPACE_NAME
              AND cdf.CON_ID = ct.CON_ID
              AND (ct.%s = :1 or (ct.CON$NAME is null and ct.CON_ID = 0))) df,
           (SELECT TRUNC(SUM(BYTES)) AS FREE,
                   FILE_ID
            FROM CDB_FREE_SPACE
            GROUP BY FILE_ID) f
      WHERE df.FILE_ID = f.FILE_ID (+)
        AND df.TABLESPACE_NAME = :2
      GROUP BY df.CON_NAME,
               df.TABLESPACE_NAME,
               df.CONTENTS,
               df.STATUS)`, connType)
}

func getTempQueryConnPart(connType string) string {
	return fmt.Sprintf(`
SELECT JSON_ARRAYAGG(
               JSON_OBJECT(
                       TABLESPACE_NAME VALUE JSON_OBJECT(
                       'contents' VALUE CONTENTS,
                       'file_bytes' VALUE FILE_BYTES,
                       'max_bytes' VALUE MAX_BYTES,
                       'free_bytes' VALUE FREE_BYTES,
                       'used_bytes' VALUE USED_BYTES,
                       'used_pct_max' VALUE USED_PCT_MAX,
                       'used_file_pct' VALUE USED_FILE_PCT,
                       'used_from_max_pct' VALUE USED_FROM_MAX_PCT,
                       'status' VALUE STATUS
                   )
                   ) RETURNING CLOB
           )
FROM (SELECT Y.NAME                                   AS TABLESPACE_NAME,
             Y.CONTENTS                               AS CONTENTS,
             NVL(SUM(Y.BYTES), 0)                     AS FILE_BYTES,
             NVL(SUM(Y.MAX_BYTES), 0)                 AS MAX_BYTES,
             NVL(MAX(NVL(Y.FREE_BYTES, 0)), 0)        AS FREE_BYTES,
             NVL(SUM(Y.BYTES) - MAX(Y.FREE_BYTES), 0) AS USED_BYTES,
             ROUND(CASE SUM(Y.MAX_BYTES)
                       WHEN 0 THEN 0
                       ELSE (SUM(Y.BYTES) / SUM(Y.MAX_BYTES) * 100)
                       END, 2)                        AS USED_PCT_MAX,
             ROUND(CASE SUM(Y.BYTES)
                       WHEN 0 THEN 0
                       ELSE (NVL(SUM(Y.BYTES) - MAX(Y.FREE_BYTES), 0)) / SUM(Y.BYTES) * 100
                       END, 2)                        AS USED_FILE_PCT,
             ROUND(CASE SUM(Y.MAX_BYTES)
                       WHEN 0 THEN 0
                       ELSE NVL(SUM(Y.BYTES) - MAX(Y.FREE_BYTES), 0) / SUM(Y.MAX_BYTES) * 100
                       END, 2)                        AS USED_FROM_MAX_PCT,
             CASE Y.STATUS
                 WHEN 'ONLINE' THEN 1
                 WHEN 'OFFLINE' THEN 2
                 WHEN 'READ ONLY' THEN 3
                 ELSE 0
                 END                                  AS STATUS
      FROM (SELECT ct.CON$NAME                  AS CON_NAME,
                   ctf.TABLESPACE_NAME          AS NAME,
                   ct.CONTENTS,
                   ctf.STATUS                   AS STATUS,
                   ctf.BYTES                    AS BYTES,
                   (SELECT ((f.TOTAL_BLOCKS - s.TOT_USED_BLOCKS) * vp.VALUE)
                    FROM (SELECT CON_ID,
                                 TABLESPACE_NAME,
                                 SUM(USED_BLOCKS) TOT_USED_BLOCKS
                          FROM V$SORT_SEGMENT
                          WHERE TABLESPACE_NAME != 'DUMMY'
                          GROUP BY CON_ID,
                                   TABLESPACE_NAME) s,
                         (SELECT CON_ID,
                                 TABLESPACE_NAME,
                                 SUM(BLOCKS) TOTAL_BLOCKS
                          FROM CDB_TEMP_FILES
                          WHERE TABLESPACE_NAME != 'DUMMY'
                          GROUP BY CON_ID,
                                   TABLESPACE_NAME) f,
                         (SELECT VALUE
                          FROM V$PARAMETER
                          WHERE NAME = 'db_block_size') vp
                    WHERE f.TABLESPACE_NAME = s.TABLESPACE_NAME
                      AND f.TABLESPACE_NAME = ctf.TABLESPACE_NAME
                      AND f.CON_ID = s.CON_ID
                      AND f.CON_ID = ct.CON_ID) AS FREE_BYTES,
                   CASE
                       WHEN ctf.MAXBYTES = 0 THEN ctf.BYTES
                       ELSE ctf.MAXBYTES
                       END                      AS MAX_BYTES
            FROM CDB_TEMP_FILES ctf,
                 CDB_TABLESPACES ct
            WHERE ctf.TABLESPACE_NAME = ct.TABLESPACE_NAME
              AND ctf.CON_ID = ct.CON_ID
              AND (ct.%s = :1 or (ct.CON$NAME is null and ct.CON_ID = 0))
              AND ctf.TABLESPACE_NAME = :2) Y
      GROUP BY Y.CON_NAME, Y.NAME, Y.CONTENTS, Y.STATUS)`, connType)
}
