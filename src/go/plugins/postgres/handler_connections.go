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
	"errors"

	"git.zabbix.com/ap/plugin-support/zbxerr"
	"github.com/jackc/pgx/v4"
)

// connectionsHandler executes select from pg_stat_activity command and returns JSON if all is OK or nil otherwise.
func connectionsHandler(ctx context.Context, conn PostgresClient,
	_ string, _ map[string]string, _ ...string) (interface{}, error) {
	var connectionsJSON string

	query := `SELECT row_to_json(T)
	FROM (
		SELECT
			sum(CASE WHEN state = 'active' THEN 1 ELSE 0 END) AS active,
			sum(CASE WHEN state = 'idle' THEN 1 ELSE 0 END) AS idle,
			sum(CASE WHEN state = 'idle in transaction' THEN 1 ELSE 0 END) AS idle_in_transaction,
			sum(CASE WHEN state = 'idle in transaction (aborted)' THEN 1 ELSE 0 END) AS idle_in_transaction_aborted,
			sum(CASE WHEN state = 'fastpath function call' THEN 1 ELSE 0 END) AS fastpath_function_call,
			sum(CASE WHEN state = 'disabled' THEN 1 ELSE 0 END) AS disabled,
			count(*) AS total,
			count(*)*100/(SELECT current_setting('max_connections')::int) AS total_pct,
			sum(CASE WHEN wait_event IS NOT NULL THEN 1 ELSE 0 END) AS waiting,
			(SELECT count(*) FROM pg_prepared_xacts) AS prepared
		FROM pg_stat_activity WHERE datid IS NOT NULL AND state IS NOT NULL) T;`

	row, err := conn.QueryRow(ctx, query)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&connectionsJSON)
	if err != nil {
		if errors.Is(err, pgx.ErrNoRows) {
			return nil, zbxerr.ErrorEmptyResult.Wrap(err)
		}

		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	return connectionsJSON, nil
}
