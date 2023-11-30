/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
	"fmt"
	"strings"

	"git.zabbix.com/ap/plugin-support/zbxerr"
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

	query, err := getQuery(params)
	if err != nil {
		return nil, err
	}

	row, err := conn.QueryRow(ctx, query)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&tablespaces)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	// Add leading zeros for floats: ".03" -> "0.03".
	// Oracle JSON functions are not RFC 4627 compliant.
	// There should be a better way to do that, but I haven't come up with it ¯\_(ツ)_/¯
	tablespaces = strings.ReplaceAll(tablespaces, "\":.", "\":0.")

	return tablespaces, nil
}

func getQuery(params map[string]string) (string, error) {
	ts := params["Tablespace"]
	t := params["Type"]
	conn := params["Conname"]

	if ts == "" && t == "" {
		if conn == "" {
			return getFullQuery(), nil
		}

		return getFullQueryConn(conn), nil
	}

	var err error
	ts, t, err = prepValues(ts, t)
	if err != nil {
		return "", zbxerr.ErrorInvalidParams.Wrap(err)
	}

	switch t {
	case perm, undo:
		if conn != "" {
			return getPermQueryConnPart(conn, ts), nil
		}

		return getPermQueryPart(ts), nil
	case temp:
		if conn != "" {
			return getTempQueryConnPart(conn, ts), nil
		}

		return getTempQueryPart(ts), nil
	default:
		return "", zbxerr.ErrorInvalidParams.Wrap(fmt.Errorf("incorrect table-space type %s", t))
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

		return "", "", fmt.Errorf("incorrect type %s", t)
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
GROUP BY CON_NAME;
`
}

func getFullQueryConn(conName string) string {
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
              AND (ct.CON$NAME = '%s' or (ct.CON$NAME is null and ct.CON_ID = 0))) df,
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
              AND ((ct.CON$NAME = '%s') or (ct.CON$NAME is null and ct.CON_ID = 0))) Y
      GROUP BY Y.CON_NAME,
               Y.NAME,
               Y.CONTENTS,
               Y.STATUS);
`, conName, conName)
}

func getPermQueryPart(name string) string {
	return fmt.Sprintf(`
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
        AND df.TABLESPACE_NAME = '%s'
      GROUP BY df.CON_NAME,
               df.TABLESPACE_NAME,
               df.CONTENTS,
               df.STATUS)
GROUP BY CON_NAME
`, name)
}

func getTempQueryPart(name string) string {
	return fmt.Sprintf(`
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
              AND ctf.TABLESPACE_NAME = '%s') Y
      GROUP BY Y.CON_NAME, Y.NAME, Y.CONTENTS, Y.STATUS)
GROUP BY CON_NAME
`, name)
}

func getPermQueryConnPart(conName, name string) string {
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
              AND (ct.CON$NAME = '%s' or (ct.CON$NAME is null and ct.CON_ID = 0))) df,
           (SELECT TRUNC(SUM(BYTES)) AS FREE,
                   FILE_ID
            FROM CDB_FREE_SPACE
            GROUP BY FILE_ID) f
      WHERE df.FILE_ID = f.FILE_ID (+)
        AND df.TABLESPACE_NAME = '%s'
      GROUP BY df.CON_NAME,
               df.TABLESPACE_NAME,
               df.CONTENTS,
               df.STATUS)
`, conName, name)
}

func getTempQueryConnPart(conName, name string) string {
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
              AND ((ct.CON$NAME = '%s') or (ct.CON$NAME is null and ct.CON_ID = 0))
              AND ctf.TABLESPACE_NAME = '%s') Y
      GROUP BY Y.CON_NAME, Y.NAME, Y.CONTENTS, Y.STATUS)`, conName, name)
}
