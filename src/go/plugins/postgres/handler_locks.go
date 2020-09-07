/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package postgres

import (
	"context"
)

const (
	keyPostgresLocks = "pgsql.locks"
)

// locksHandler executes select from pg_stat_database command and returns JSON if all is OK or nil otherwise.
func (p *Plugin) locksHandler(conn *postgresConn, key string, params []string) (interface{}, error) {
	var locksJSON string
	var err error
	query := `
WITH T AS
	(SELECT db.datname dbname,
			lower(replace(Q.mode, 'Lock', '')) AS MODE,
			coalesce(T.qty, 0) val
	FROM pg_database db
	JOIN (
			VALUES ('AccessShareLock') ,('RowShareLock') ,('RowExclusiveLock') ,('ShareUpdateExclusiveLock') ,('ShareLock') ,('ShareRowExclusiveLock') ,('ExclusiveLock') ,('AccessExclusiveLock')) Q(MODE) ON TRUE NATURAL
	LEFT JOIN
		(SELECT datname,
			MODE,
			count(MODE) qty
		FROM pg_locks lc
		RIGHT JOIN pg_database db ON db.oid = lc.database
		GROUP BY 1, 2) T
	WHERE NOT db.datistemplate
	ORDER BY 1, 2)
SELECT json_object_agg(dbname, row_to_json(T2))
FROM
	(SELECT dbname,
			sum(val) AS total,
			sum(CASE
					WHEN MODE = 'accessexclusive' THEN val
				END) AS accessexclusive,
			sum(CASE
					WHEN MODE = 'accessshare' THEN val
				END) AS accessshare,
			sum(CASE
					WHEN MODE = 'exclusive' THEN val
				END) AS EXCLUSIVE,
			sum(CASE
					WHEN MODE = 'rowexclusive' THEN val
				END) AS rowexclusive,
			sum(CASE
					WHEN MODE = 'rowshare' THEN val
				END) AS rowshare,
			sum(CASE
					WHEN MODE = 'share' THEN val
				END) AS SHARE,
			sum(CASE
					WHEN MODE = 'sharerowexclusive' THEN val
				END) AS sharerowexclusive,
			sum(CASE
					WHEN MODE = 'shareupdateexclusive' THEN val
				END) AS shareupdateexclusive
	FROM T
	GROUP BY dbname) T2`

	err = conn.postgresPool.QueryRow(context.Background(), query).Scan(&locksJSON)
	if err != nil {
		p.Errf(err.Error())
		return nil, errorCannotFetchData
	}
	if len(locksJSON) == 0 {
		return nil, errorCannotParseData
	}

	return locksJSON, nil
}
