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
	keyPostgresOldestXid             = "pgsql.oldest.xid"
	keyPostgresOldestTransactionTime = "pgsql.oldest.transaction.time"
)

// oldestHandler gets age of the oldest xid and transaction if all is OK or nil otherwise.
func (p *Plugin) oldestHandler(conn *postgresConn, params []string) (interface{}, error) {
	var resultTransactionTime float64
	var resultXID int
	var result float64

	var query string
	var key string
	var err error

	if len(params) == 0 {
		return nil, errorEmptyParam
	}
	key = params[0]

	switch key {

	case keyPostgresOldestXid:
		query = `SELECT greatest(max(age(backend_xmin)), max(age(backend_xid))) FROM pg_catalog.pg_stat_activity`
		err = conn.postgresPool.QueryRow(context.Background(), query).Scan(&resultXID)
		result = float64(resultXID)
	case keyPostgresOldestTransactionTime:
		query = `SELECT 
	CASE 
		WHEN extract(epoch from max(now() - xact_start)) IS NOT NULL AND extract(epoch FROM max(now() - xact_start))>0 
			THEN extract(epoch from max(now() - xact_start)) 
		ELSE 0 END 
	FROM pg_catalog.pg_stat_activity 
	WHERE pid NOT IN (SELECT pid FROM pg_stat_replication) AND pid <> pg_backend_pid();`
		err = conn.postgresPool.QueryRow(context.Background(), query).Scan(&resultTransactionTime)
		result = resultTransactionTime
	}
	if err != nil {
		p.Errf(err.Error())
		return nil, errorCannotFetchData
	}
	return result, nil
}
