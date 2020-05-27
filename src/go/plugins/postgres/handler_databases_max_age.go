/* /*
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
	keyPostgresDatabasesAge = "pgsql.db.age"
)

// databasesAgeHandler gets age of each database respectively or nil otherwise.
func (p *Plugin) databasesAgeHandler(conn *postgresConn, key string, params []string) (interface{}, error) {
	var countAge int64
	// for now we are expecting only database name as a param
	if len(params) == 0 {
		return nil, errorFourthParamEmpty
	}
	if len(params[0]) == 0 {
		return nil, errorFourthParamLen
	}

	err := conn.postgresPool.QueryRow(context.Background(),
		`SELECT age(datfrozenxid)
		FROM pg_catalog.pg_database
   		WHERE datistemplate = false
			 AND datname = $1;`,
		params[0]).Scan(&countAge)

	if err != nil {
		if err == pgx.ErrNoRows {
			p.Errf(err.Error())
			return nil, errorEmptyResult
		}
		p.Errf(err.Error())
		return nil, errorCannotFetchData
	}
	return countAge, nil
}
