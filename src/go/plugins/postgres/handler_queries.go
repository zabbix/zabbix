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
	"fmt"
	"strconv"

	"git.zabbix.com/ap/plugin-support/zbxerr"
)

// queriesHandler executes select from pg_database command and returns JSON if all is OK or nil otherwise.
func queriesHandler(ctx context.Context, conn PostgresClient,
	_ string, params map[string]string, _ ...string) (interface{}, error) {
	var queriesJSON string

	period, err := strconv.Atoi(params["TimePeriod"])
	if err != nil {
		return nil, zbxerr.ErrorInvalidParams.Wrap(
			fmt.Errorf("TimePeriod must be an integer, %s", err.Error()),
		)
	}

	if period < 1 {
		return nil, zbxerr.ErrorInvalidParams.Wrap(
			fmt.Errorf("TimePeriod must be greater than 0"),
		)
	}

	exp := `^(\\s*(--[^\\n]*\\n|/\\*.*\\*/|\\n))*(autovacuum|VACUUM|ANALYZE|REINDEX|CLUSTER|CREATE|ALTER|TRUNCATE|DROP)`
	query := fmt.Sprintf(`WITH T AS (
		SELECT
			db.datname,
			coalesce(T.query_time_max, 0) query_time_max,
			coalesce(T.tx_time_max, 0) tx_time_max,
			coalesce(T.mro_time_max, 0) mro_time_max,
			coalesce(T.query_time_sum, 0) query_time_sum,
			coalesce(T.tx_time_sum, 0) tx_time_sum,
			coalesce(T.mro_time_sum, 0) mro_time_sum,
			coalesce(T.query_slow_count, 0) query_slow_count,
			coalesce(T.tx_slow_count, 0) tx_slow_count,
			coalesce(T.mro_slow_count, 0) mro_slow_count
		FROM
			pg_database db NATURAL
			LEFT JOIN (
				SELECT
					datname,
					extract(
						epoch
						FROM
							now()
					) :: integer ts,
					coalesce(
						max(
							extract(
								'epoch'
								FROM
									(clock_timestamp() - query_start)
							) :: integer * (
								state NOT IN (
									'idle',
									'idle in transaction',
									'idle in transaction (aborted)'
								)
								AND query !~* E'%s'
							) :: integer
						),
						0
					) query_time_max,
					coalesce(
						max(
							extract(
								'epoch'
								FROM
									(clock_timestamp() - query_start)
							) :: integer * (
								state NOT IN ('idle')
								AND query !~* E'%s'
							) :: integer
						),
						0
					) tx_time_max,
					coalesce(
						max(
							extract(
								'epoch'
								FROM
									(clock_timestamp() - query_start)
							) :: integer * (
								state NOT IN ('idle')
								AND query ~* E'%s'
							) :: integer
						),
						0
					) mro_time_max,
					coalesce(
						sum(
							extract(
								'epoch'
								FROM
									(clock_timestamp() - query_start)
							) :: integer * (
								state NOT IN (
									'idle',
									'idle in transaction',
									'idle in transaction (aborted)'
								)
								AND query !~* E'%s'
							) :: integer
						),
						0
					) query_time_sum,
					coalesce(
						sum(
							extract(
								'epoch'
								FROM
									(clock_timestamp() - query_start)
							) :: integer * (
								state NOT IN ('idle')
								AND query !~* E'%s'
							) :: integer
						),
						0
					) tx_time_sum,
					coalesce(
						sum(
							extract(
								'epoch'
								FROM
									(clock_timestamp() - query_start)
							) :: integer * (
								state NOT IN ('idle')
								AND query ~* E'%s'
							) :: integer
						),
						0
					) mro_time_sum,
					coalesce(
						sum(
							(
								extract(
									'epoch'
									FROM
										(clock_timestamp() - query_start)
								) > % d
							) :: integer * (
								state NOT IN (
									'idle',
									'idle in transaction',
									'idle in transaction (aborted)'
								)
								AND query !~* E'%s'
							) :: integer
						),
						0
					) query_slow_count,
					coalesce(
						sum(
							(
								extract(
									'epoch'
									FROM
										(clock_timestamp() - query_start)
								) > % d
							) :: integer * (
								state NOT IN ('idle')
								AND query !~* E'%s'
							) :: integer
						),
						0
					) tx_slow_count,
					coalesce(
						sum(
							(
								extract(
									'epoch'
									FROM
										(clock_timestamp() - query_start)
								) > % d
							) :: integer * (
								state NOT IN ('idle')
								AND query ~* E'%s'
							) :: integer
						),
						0
					) mro_slow_count
				FROM
					pg_stat_activity
				WHERE
					pid <> pg_backend_pid()
				GROUP BY
					1
			) T
		WHERE
			NOT db.datistemplate
	)
	SELECT
		json_object_agg(datname, row_to_json(T))
	FROM
		T`,
		exp, exp, exp, exp, exp, exp, period, exp, period, exp, period, exp)

	row, err := conn.QueryRow(ctx, query)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&queriesJSON)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	if len(queriesJSON) == 0 {
		return nil, zbxerr.ErrorCannotParseResult
	}

	return queriesJSON, nil
}
