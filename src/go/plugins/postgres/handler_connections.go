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

	"github.com/jackc/pgx/v4"
)

const (
	keyPostgresConnections = "pgsql.connections"
)

// connectionsHandler executes select from pg_stat_activity command and returns JSON if all is OK or nil otherwise.
func (p *Plugin) connectionsHandler(conn *postgresConn, key string, params []string) (interface{}, error) {
	var connectionsJSON string
	var err error
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
		FROM pg_stat_activity WHERE datid is not NULL) T;`

	err = conn.postgresPool.QueryRow(context.Background(), query).Scan(&connectionsJSON)

	if err != nil {
		if err == pgx.ErrNoRows {
			p.Errf(err.Error())
			return nil, errorEmptyResult
		}
		p.Errf(err.Error())
		return nil, errorCannotFetchData
	}

	return connectionsJSON, nil
}
