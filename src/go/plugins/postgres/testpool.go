//go:build !windows
// +build !windows

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
	"database/sql"
	"fmt"
	"os"
	"time"

	"git.zabbix.com/ap/plugin-support/log"
)

var sharedConn *PGConn

func getConnPool() (*PGConn, error) {
	return sharedConn, nil
}

func getEnv() (pgAddr, pgUser, pgPwd, pgDb string) {
	pgAddr = os.Getenv("PG_ADDR")
	pgUser = os.Getenv("PG_USER")
	pgPwd = os.Getenv("PG_PWD")
	pgDb = os.Getenv("PG_DB")

	if pgAddr == "" {
		pgAddr = "localhost:5432"
	}
	if pgUser == "" {
		pgUser = "postgres"
	}
	if pgPwd == "" {
		pgPwd = "postgres"
	}
	if pgDb == "" {
		pgDb = "postgres"
	}

	return
}

func createConnection() error {
	pgAddr, pgUser, pgPwd, pgDb := getEnv()

	connString := fmt.Sprintf("postgresql://%s:%s@%s/%s", pgUser, pgPwd, pgAddr, pgDb)

	newConn, err := sql.Open("pgx", connString)
	if err != nil {
		log.Critf("[createConnection] cannot create connection to Postgres: %s", err.Error())

		return err
	}

	var version int

	err = newConn.QueryRow(`select current_setting('server_version_num');`).Scan(&version)
	if err != nil {
		log.Critf("[createConnection] cannot get Postgres version: %s", err.Error())

		return err
	}

	sharedConn = &PGConn{
		client:         newConn,
		lastTimeAccess: time.Now(),
		version:        version,
		callTimeout:    30,
	}

	return nil
}
