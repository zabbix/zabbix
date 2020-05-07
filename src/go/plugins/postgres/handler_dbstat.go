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
	"fmt"

	"github.com/jackc/pgx/v4"
)

const (
	keyPostgresStatSum = "pgsql.dbstat.sum"
	keyPostgresStat    = "pgsql.dbstat"
)

// dbStatHandler executes select from pg_catalog.pg_stat_database command for each database and returns JSON if all is OK or nil otherwise.
func (p *Plugin) dbStatHandler(conn *postgresConn, key string, params []string) (interface{}, error) {
	var statJSON, query string
	var err error

	switch key {
	case keyPostgresStatSum:
		query = `
  SELECT row_to_json (T)
    FROM  (
      SELECT
        sum(numbackends) as numbackends
      , sum(xact_commit) as xact_commit
      , sum(xact_rollback) as xact_rollback
      , sum(blks_read) as blks_read
      , sum(blks_hit) as blks_hit
      , sum(tup_returned) as tup_returned
      , sum(tup_fetched) as tup_fetched
      , sum(tup_inserted) as tup_inserted
      , sum(tup_updated) as tup_updated
      , sum(tup_deleted) as tup_deleted
      , sum(conflicts) as conflicts
      , sum(temp_files) as temp_files
      , sum(temp_bytes) as temp_bytes
      , sum(deadlocks) as deadlocks
      , %s as checksum_failures
      , sum(blk_read_time) as blk_read_time
      , sum(blk_write_time) as blk_write_time
      FROM pg_catalog.pg_stat_database
    ) T ;`
		if conn.version >= 120000 {
			query = fmt.Sprintf(query, "sum(checksum_failures)")
		} else {
			query = fmt.Sprintf(query, "null")
		}

	case keyPostgresStat:
		query = `
  SELECT json_object_agg(coalesce (datname,'null'), row_to_json(T))
    FROM  (
      SELECT
        datname
      , numbackends as numbackends
      , xact_commit as xact_commit
      , xact_rollback as xact_rollback
      , blks_read as blks_read
      , blks_hit as blks_hit
      , tup_returned as tup_returned
      , tup_fetched as tup_fetched
      , tup_inserted as tup_inserted
      , tup_updated as tup_updated
      , tup_deleted as tup_deleted
      , conflicts as conflicts
      , temp_files as temp_files
      , temp_bytes as temp_bytes
      , deadlocks as deadlocks
      , %s as checksum_failures
      , blk_read_time as blk_read_time
      , blk_write_time as blk_write_time
      FROM pg_catalog.pg_stat_database
    ) T ;`
		if conn.version >= 120000 {
			query = fmt.Sprintf(query, "checksum_failures")
		} else {
			query = fmt.Sprintf(query, "null")
		}
	}

	err = conn.postgresPool.QueryRow(context.Background(), query).Scan(&statJSON)
	if err != nil {
		if err == pgx.ErrNoRows {
			p.Errf(err.Error())
			return nil, errorEmptyResult
		}
		p.Errf(err.Error())
		return nil, errorCannotFetchData
	}

	return statJSON, nil
}
