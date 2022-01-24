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

package postgres

import (
	"context"
	"database/sql"
	"errors"
	"strconv"

	"github.com/jackc/pgx/v4"
	"zabbix.com/pkg/zbxerr"
)

// replicationHandler gets info about recovery state if all is OK or nil otherwise.
func replicationHandler(ctx context.Context, conn PostgresClient,
	key string, _ map[string]string, _ ...string) (interface{}, error) {
	var (
		replicationResult int64
		status            int
		query             string
		stringResult      sql.NullString
		inRecovery        bool
	)

	switch key {
	case keyReplicationStatus:
		row, err := conn.QueryRow(ctx, `SELECT pg_is_in_recovery()`)
		if err != nil {
			return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
		}

		err = row.Scan(&inRecovery)
		if err != nil {
			return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
		}

		if inRecovery {
			row, err = conn.QueryRow(ctx, `SELECT COUNT(*) FROM pg_stat_wal_receiver`)
			if err != nil {
				return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
			}

			err = row.Scan(&status)
			if err != nil {
				if errors.Is(err, pgx.ErrNoRows) {
					return nil, zbxerr.ErrorEmptyResult.Wrap(err)
				}

				return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
			}
		} else {
			status = 2
		}

		return strconv.Itoa(status), nil

	case keyReplicationLagSec:
		query = `SELECT
					CASE
		  				WHEN NOT pg_is_in_recovery() OR pg_last_wal_receive_lsn() = pg_last_wal_replay_lsn() THEN 0
		  				ELSE COALESCE(EXTRACT(EPOCH FROM now() - pg_last_xact_replay_timestamp())::integer, 0)
					END AS lag;`
	case keyReplicationLagB:
		row, err := conn.QueryRow(ctx, `SELECT pg_is_in_recovery()`)
		if err != nil {
			return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
		}

		err = row.Scan(&inRecovery)
		if err != nil {
			if errors.Is(err, pgx.ErrNoRows) {
				return nil, zbxerr.ErrorEmptyResult.Wrap(err)
			}

			return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
		}

		if inRecovery {
			query = `SELECT pg_catalog.pg_wal_lsn_diff (pg_last_wal_receive_lsn(), pg_last_wal_replay_lsn());`
			row, err = conn.QueryRow(ctx, query)

			if err != nil {
				return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
			}

			err = row.Scan(&replicationResult)
			if err != nil {
				if errors.Is(err, pgx.ErrNoRows) {
					return nil, zbxerr.ErrorEmptyResult.Wrap(err)
				}

				return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
			}
		} else {
			replicationResult = 0
		}

		return replicationResult, nil

	case keyReplicationRecoveryRole:
		query = `SELECT pg_is_in_recovery()::int`

	case keyReplicationCount:
		query = `SELECT COUNT(DISTINCT client_addr) + COALESCE(SUM(CASE WHEN client_addr IS NULL THEN 1 ELSE 0 END), 0) FROM pg_stat_replication;`

	case keyReplicationProcessInfo:
		query = `SELECT json_object_agg(application_name, row_to_json(T))
				   FROM (
						SELECT
						    application_name,
							EXTRACT(epoch FROM COALESCE(flush_lag,'0'::interval)) AS flush_lag, 
							EXTRACT(epoch FROM COALESCE(replay_lag,'0'::interval)) AS replay_lag,
							EXTRACT(epoch FROM COALESCE(write_lag, '0'::interval)) AS write_lag
						FROM pg_stat_replication
					) T; `
		row, err := conn.QueryRow(ctx, query)

		if err != nil {
			return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
		}

		err = row.Scan(&stringResult)
		if err != nil {
			if errors.Is(err, pgx.ErrNoRows) {
				return nil, zbxerr.ErrorEmptyResult.Wrap(err)
			}

			return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
		}

		return stringResult.String, nil
	}

	row, err := conn.QueryRow(ctx, query)

	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&replicationResult)
	if err != nil {
		if errors.Is(err, pgx.ErrNoRows) {
			return nil, zbxerr.ErrorEmptyResult.Wrap(err)
		}

		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	return replicationResult, nil
}
