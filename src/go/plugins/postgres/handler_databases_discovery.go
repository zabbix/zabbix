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
	keyPostgresDiscoveryDatabases = "pgsql.db.discovery"
)

// databasesDiscoveryHandler gets names of all databases and returns JSON if all is OK or nil otherwise.
func (p *Plugin) databasesDiscoveryHandler(conn *postgresConn, key string, params []string) (interface{}, error) {
	var databasesJSON string

	query := `SELECT json_build_object ('data',json_agg(json_build_object('{#DBNAME}',d.datname)))
				FROM pg_database d
			   WHERE NOT datistemplate
				 AND datallowconn;`

	err := conn.postgresPool.QueryRow(context.Background(), query).Scan(&databasesJSON)
	if err != nil {
		if err == pgx.ErrNoRows {
			p.Errf(err.Error())
			return nil, errorEmptyResult
		}
		p.Errf(err.Error())
		return nil, errorCannotFetchData
	}

	return databasesJSON, nil
}
