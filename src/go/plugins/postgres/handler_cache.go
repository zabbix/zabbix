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
	keyPostgresCache = "pgsql.cache.hit"
)

// cacheHandler finds cache hit percent and returns int64 if all is OK or nil otherwise.
func (p *Plugin) cacheHandler(conn *postgresConn, key string, params []string) (interface{}, error) {
	var cache float64
	query := `SELECT round(sum(blks_hit)*100/sum(blks_hit+blks_read), 2) FROM pg_catalog.pg_stat_database;`

	err := conn.postgresPool.QueryRow(context.Background(), query).Scan(&cache)

	if err != nil {
		if err == pgx.ErrNoRows {
			p.Errf(err.Error())
			return nil, errorEmptyResult
		}
		p.Errf(err.Error())
		return nil, errorCannotFetchData
	}

	return cache, nil
}
