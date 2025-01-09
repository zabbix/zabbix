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
	"strings"

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

func tablespacesHandler(ctx context.Context, conn OraClient, params map[string]string,
	_ ...string) (interface{}, error) {
	var tablespaces string

	query, args, err := getTablespacesQuery(params)
	if err != nil {
		return nil, errs.Wrap(err, "failed to retrieve tabespace query")
	}

	row, err := conn.QueryRow(ctx, query, args...)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	err = row.Scan(&tablespaces)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	// Add leading zeros for floats: ".03" -> "0.03".
	// Oracle JSON functions are not RFC 4627 compliant.
	// There should be a better way to do that, but I haven't come up with it ¯\_(ツ)_/¯
	tablespaces = strings.ReplaceAll(tablespaces, "\":.", "\":0.")

	return tablespaces, nil
}

func getTablespacesQuery(params map[string]string) (string, []any, error) {
	ts := params["Tablespace"]
	t := params["Type"]
	conn := params["Conname"]

	if ts == "" && t == "" {
		if conn == "" {
			return getFullQuery(), nil, nil
		}

		return getFullQueryConn(), []any{conn, conn}, nil
	}

	var err error
	ts, t, err = prepValues(ts, t)
	if err != nil {
		return "", nil, errs.WrapConst(err, zbxerr.ErrorInvalidParams)
	}

	switch t {
	case perm, undo:
		if conn != "" {
			return getPermQueryConnPart(), []any{conn, ts}, nil
		}

		return getPermQueryPart(), []any{ts}, nil
	case temp:
		if conn != "" {
			return getTempQueryConnPart(), []any{conn, ts}, nil
		}

		return getTempQueryPart(), []any{ts}, nil
	default:
		return "", nil, errs.Wrapf(zbxerr.ErrorInvalidParams, "incorrect table-space type %s", t)
	}
}

func prepValues(ts, t string) (outTableSpace string, outType string, err error) {
	if ts != "" && t != "" {
		return ts, t, nil
	}

	if ts == "" {
		if t == perm {
			return defaultPERMTS, t, nil
		}

		if t == temp {
			return defaultTEMPTS, t, nil
		}

		return "", "", errs.Errorf("incorrect type %s", t)
	}

	return ts, perm, nil
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
FROM (SELECT df.CON_NAME,
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
      SELECT Y.CON_NAME,
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

func getFullQueryConn() string {
	return `
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
              AND (ct.CON$NAME = :1 or (ct.CON$NAME is null and ct.CON_ID = 0))) df,
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
              AND ((ct.CON$NAME = :2) or (ct.CON$NAME is null and ct.CON_ID = 0))) Y
      GROUP BY Y.CON_NAME,
               Y.NAME,
               Y.CONTENTS,
               Y.STATUS)`
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
FROM (SELECT df.CON_NAME,
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
FROM (SELECT Y.CON_NAME,
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

func getPermQueryConnPart() string {
	return `
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
              AND (ct.CON$NAME = :1 or (ct.CON$NAME is null and ct.CON_ID = 0))) df,
           (SELECT TRUNC(SUM(BYTES)) AS FREE,
                   FILE_ID
            FROM CDB_FREE_SPACE
            GROUP BY FILE_ID) f
      WHERE df.FILE_ID = f.FILE_ID (+)
        AND df.TABLESPACE_NAME = :2 
      GROUP BY df.CON_NAME,
               df.TABLESPACE_NAME,
               df.CONTENTS,
               df.STATUS)`
}

func getTempQueryConnPart() string {
	return `
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
              AND ((ct.CON$NAME = :1) or (ct.CON$NAME is null and ct.CON_ID = 0))
              AND ctf.TABLESPACE_NAME = :2) Y
      GROUP BY Y.CON_NAME, Y.NAME, Y.CONTENTS, Y.STATUS)`
}
