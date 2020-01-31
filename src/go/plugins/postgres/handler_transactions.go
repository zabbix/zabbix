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
	"database/sql"
)

const (
	keyPostgresTransactions = "pgsql.transactions"
)

// transactionsHandler executes select from pg_stat_activity command and returns JSON if all is OK or nil otherwise.
func (p *Plugin) transactionsHandler(conn *postgresConn, params []string) (interface{}, error) {
	var transactionJSON string

	multiline := `SELECT row_to_json(T) FROM
	 (SELECT coalesce(extract(epoch FROM max(CASE WHEN state = 'idle in transaction' THEN age(now(), query_start) END)), 0) AS idle,
	 coalesce(extract(epoch FROM max(CASE WHEN state <> 'idle in transaction' AND state <> 'idle' THEN age(now(), query_start) END)), 0) AS active,
	 coalesce(extract(epoch FROM max(CASE WHEN wait_event IS NOT NULL THEN age(now(), query_start) END)), 0) AS waiting,
	(SELECT coalesce(extract(epoch FROM max(age(now(), prepared))), 0) FROM pg_prepared_xacts) AS prepared 
	 FROM pg_stat_activity) T;`

	err := conn.postgresPool.QueryRow(context.Background(), multiline).Scan(&transactionJSON)
	if err != nil {
		if err == sql.ErrNoRows {
			p.Errf(err.Error())
			return nil, errorEmptyResult
		}
		p.Errf(err.Error())
		return nil, errorCannotFetchData
	}

	return transactionJSON, nil
}
