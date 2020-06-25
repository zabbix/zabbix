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
	"strconv"

	"github.com/jackc/pgx/v4"
)

const (
	keyPostgresReplicationCount                          = "pgsql.replication.count"
	keyPostgresReplicationStatus                         = "pgsql.replication.status"
	keyPostgresReplicationLagSec                         = "pgsql.replication.lag.sec"
	keyPostgresReplicationLagB                           = "pgsql.replication.lag.b"
	keyPostgresReplicationRecoveryRole                   = "pgsql.replication.recovery_role"
	keyPostgresReplicationMasterDiscoveryApplicationName = "pgsql.replication.master.discovery.application_name"
)

// replicationHandler gets info about recovery state if all is OK or nil otherwise.
func (p *Plugin) replicationHandler(conn *postgresConn, key string, params []string) (interface{}, error) {
	var replicationResult int64
	var status int
	var query, stringResult string
	var inRecovery bool
	var err error

	switch key {
	case keyPostgresReplicationStatus:

		err = conn.postgresPool.QueryRow(context.Background(), `SELECT pg_is_in_recovery()`).Scan(&inRecovery)
		if err != nil {
			p.Errf(err.Error())
			return nil, errorCannotFetchData
		}
		if inRecovery {
			err = conn.postgresPool.QueryRow(context.Background(), `SELECT COUNT(*) FROM pg_stat_wal_receiver`).Scan(&status)
			if err != nil {
				if err == pgx.ErrNoRows {
					p.Errf(err.Error())
					return nil, errorEmptyResult
				}
				p.Errf(err.Error())
				return nil, errorCannotFetchData
			}
		} else {
			status = 2
		}
		return strconv.Itoa(status), nil

	case keyPostgresReplicationLagSec:
		query = `SELECT
  					CASE
    					WHEN pg_last_wal_receive_lsn() = pg_last_wal_replay_lsn() THEN 0
						ELSE COALESCE(EXTRACT(EPOCH FROM now() - pg_last_xact_replay_timestamp())::integer, 0)
  					END as lag`
	case keyPostgresReplicationLagB:
		err = conn.postgresPool.QueryRow(context.Background(), `SELECT pg_is_in_recovery()`).Scan(&inRecovery)
		if err != nil {
			if err == pgx.ErrNoRows {
				p.Errf(err.Error())
				return nil, errorEmptyResult
			}
			p.Errf(err.Error())
			return nil, errorCannotFetchData
		}
		if inRecovery {
			query = `SELECT pg_catalog.pg_wal_lsn_diff (received_lsn, pg_last_wal_replay_lsn())
						   FROM pg_stat_wal_receiver;`
			err = conn.postgresPool.QueryRow(context.Background(), query).Scan(&replicationResult)
			if err != nil {
				if err == pgx.ErrNoRows {
					p.Errf(err.Error())
					return nil, errorEmptyResult
				}
				p.Errf(err.Error())
				return nil, errorCannotFetchData
			}
		} else {
			replicationResult = 0
		}
		return replicationResult, nil

	case keyPostgresReplicationRecoveryRole:
		query = `SELECT pg_is_in_recovery()::int`

	case keyPostgresReplicationCount:
		query = `SELECT count(*) FROM pg_stat_replication`

	case keyPostgresReplicationMasterDiscoveryApplicationName:
		query = `SELECT '{"data":'|| coalesce(json_agg(T), '[]'::json)::text || '}'
				   FROM (
						SELECT
							application_name AS "{#APPLICATION_NAME}",
        					pg_catalog.pg_wal_lsn_diff (pg_current_wal_lsn (), '0/00000000') AS master_current_wal,
        					pg_catalog.pg_wal_lsn_diff (pg_current_wal_lsn (), sent_lsn) AS master_replication_lag
						FROM pg_stat_replication
					) T`
		err = conn.postgresPool.QueryRow(context.Background(), query).Scan(&stringResult)
		if err != nil {
			if err == pgx.ErrNoRows {
				p.Errf(err.Error())
				return nil, errorEmptyResult
			}
			p.Errf(err.Error())
			return nil, errorCannotFetchData
		}
		return stringResult, nil
	}

	err = conn.postgresPool.QueryRow(context.Background(), query).Scan(&replicationResult)
	if err != nil {
		if err == pgx.ErrNoRows {
			p.Errf(err.Error())
			return nil, errorEmptyResult
		}
		p.Errf(err.Error())
		return nil, errorCannotFetchData
	}
	return replicationResult, nil

}
