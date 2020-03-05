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
	"bytes"
	"context"
	"fmt"
	"html/template"
	"io/ioutil"
	"os"
	"testing"
	"time"

	log "github.com/sirupsen/logrus"

	"github.com/jackc/pgx/v4"
	"github.com/jackc/pgx/v4/pgxpool"
	"github.com/ory/dockertest/v3"
)

var sharedConn *postgresConn

func getConnPool(t testing.TB) (*postgresConn, error) {
	return sharedConn, nil
}

func сreateConnection(connString string) error {
	newConn, err := pgxpool.Connect(context.Background(), connString)
	if err != nil {
		return err
	}
	versionPG, err := GetPostgresVersion(newConn)
	if err != nil {
		log.Panicf("[сreateConnection] cannot get Postgres version: %s", err.Error())
	}
	sharedConn = &postgresConn{postgresPool: newConn, lastTimeAccess: time.Now(), version: versionPG}
	return nil
}

func waitForPostgresAndCreateConfig(pool *dockertest.Pool, resource *dockertest.Resource, connString string) (confPath string, cleaner func()) {
	attempt := 0
	ok := false
	for attempt < 20 {
		attempt++
		conn, err := pgx.Connect(context.Background(), connString)
		if err != nil {
			log.Infof("[waitForPostgresAndCreateConfig] pgx.Connect failed: %v, waiting... (attempt %d)", err, attempt)
			time.Sleep(1 * time.Second)
			continue
		}

		_ = conn.Close(context.Background())
		ok = true
		break
	}

	if !ok {
		_ = pool.Purge(resource)
		log.Panicf("[waitForPostgresAndCreateConfig] couldn't connect to PostgreSQL")
	} else {
		log.Infoln("[TestMain] creating sharedConn of type *postgresConn")
		сreateConnection(connString)
		log.Infoln("[TestMain] sharedConn of type *postgresConn created")
	}

	tmpl, err := template.New("config").Parse(`
loglevel: debug
listen: 0.0.0.0:8080
db:url: {{.ConnString}}
`)
	if err != nil {
		_ = pool.Purge(resource)
		log.Panicf("[waitForPostgresAndCreateConfig] template.Parse failed: %v", err)
	}

	configArgs := struct {
		ConnString string
	}{
		ConnString: connString,
	}
	var configBuff bytes.Buffer
	err = tmpl.Execute(&configBuff, configArgs)
	if err != nil {
		_ = pool.Purge(resource)
		log.Panicf("[waitForPostgresAndCreateConfig] tmpl.Execute failed: %v", err)
	}

	confFile, err := ioutil.TempFile("", "config.*.yaml")
	if err != nil {
		_ = pool.Purge(resource)
		log.Panicf("[waitForPostgresAndCreateConfig] ioutil.TempFile failed: %v", err)
	}

	log.Infof("[waitForPostgresAndCreateConfig] confFile.Name = %s", confFile.Name())

	_, err = confFile.WriteString(configBuff.String())
	if err != nil {
		_ = pool.Purge(resource)
		log.Panicf("[waitForPostgresAndCreateConfig] confFile.WriteString failed: %v", err)
	}

	err = confFile.Close()
	if err != nil {
		_ = pool.Purge(resource)
		log.Panicf("[waitForPostgresAndCreateConfig] confFile.Close failed: %v", err)
	}

	cleanerFunc := func() {
		// purge the container
		err := pool.Purge(resource)
		if err != nil {
			log.Panicf("[waitForPostgresAndCreateConfig] pool.Purge failed: %v", err)
		}

		err = os.Remove(confFile.Name())
		if err != nil {
			log.Panicf("[waitForPostgresAndCreateConfig] os.Remove failed: %v", err)
		}
	}

	return confFile.Name(), cleanerFunc
}

func startPostgreSQL(versionPG uint32) (confPath string, cleaner func()) {
	pool, err := dockertest.NewPool("")
	if err != nil {
		log.Panicf("[startPostgreSQL] dockertest.NewPool failed: %v", err)
	}
	resource, err := pool.Run(
		"postgres", fmt.Sprint(versionPG),
		[]string{
			"POSTGRES_DB=test_agent2",
			"POSTGRES_PASSWORD=this_is_postgres",
		},
	)
	if err != nil {
		log.Panicf("[startPostgreSQL] pool.Run failed: %v", err)
	}

	connString := "postgres://postgres:this_is_postgres@" + resource.GetHostPort("5432/tcp") + "/test_agent2"
	return waitForPostgresAndCreateConfig(pool, resource, connString)
}
