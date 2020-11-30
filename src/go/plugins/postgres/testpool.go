// +build !windows

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
	"database/sql"
	"strconv"
	"testing"
	"time"

	"zabbix.com/pkg/log"
)

var sharedConn *PostgresConn

func getConnPool(t testing.TB) (*PostgresConn, error) {
	return sharedConn, nil
}

func сreateConnection() error {
	connString := "postgresql://postgres:postgres@localhost:5432/postgres"

	newConn, err := sql.Open("pgx", connString)
	if err != nil {
		log.Critf("[сreateConnection] cannot create connection to Postgres: %s", err.Error())

		return err
	}

	versionPG, err := GetPostgresVersion(newConn)
	if err != nil {
		log.Critf("[сreateConnection] cannot get Postgres version: %s", err.Error())

		return err
	}

	version, err := strconv.Atoi(versionPG)
	if err != nil {
		log.Critf("[сreateConnection] invalid Postgres version: %s", err.Error())

		return err
	}

	sharedConn = &PostgresConn{client: newConn, lastTimeAccess: time.Now(), version: version, connString: connString, timeout: 30}

	return nil
}
