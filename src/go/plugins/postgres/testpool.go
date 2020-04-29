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

	"github.com/docker/go-connections/nat"
	"zabbix.com/pkg/log"

	"github.com/jackc/pgx/v4"
	"github.com/jackc/pgx/v4/pgxpool"
	"github.com/testcontainers/testcontainers-go"
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
		log.Critf("[сreateConnection] cannot get Postgres version: %s", err.Error())
	}
	sharedConn = &postgresConn{postgresPool: newConn, lastTimeAccess: time.Now(), version: versionPG}
	return nil
}

func waitForPostgresAndCreateConfig(pool testcontainers.Container, ctx context.Context, connString string) (confPath string, cleaner func()) {
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
		_ = pool.Terminate(ctx)
		log.Critf("[waitForPostgresAndCreateConfig] couldn't connect to PostgreSQL")
	} else {
		log.Infof("[TestMain] creating sharedConn of type *postgresConn")
		сreateConnection(connString)
		log.Infof("[TestMain] sharedConn of type *postgresConn created")
	}
	сreateConnection(connString)
	tmpl, err := template.New("config").Parse(`
loglevel: debug
listen: 0.0.0.0:8080
db:url: {{.ConnString}}
`)
	if err != nil {
		_ = pool.Terminate(ctx)
		log.Critf("[waitForPostgresAndCreateConfig] template.Parse failed: %v", err)
	}

	configArgs := struct {
		ConnString string
	}{
		ConnString: connString,
	}
	var configBuff bytes.Buffer
	err = tmpl.Execute(&configBuff, configArgs)
	if err != nil {
		_ = pool.Terminate(ctx)
		log.Critf("[waitForPostgresAndCreateConfig] tmpl.Execute failed: %v", err)
	}

	confFile, err := ioutil.TempFile("", "config.*.yaml")
	if err != nil {
		_ = pool.Terminate(ctx)
		log.Critf("[waitForPostgresAndCreateConfig] ioutil.TempFile failed: %v", err)
	}

	log.Infof("[waitForPostgresAndCreateConfig] confFile.Name = %s", confFile.Name())

	_, err = confFile.WriteString(configBuff.String())
	if err != nil {
		_ = pool.Terminate(ctx)
		log.Critf("[waitForPostgresAndCreateConfig] confFile.WriteString failed: %v", err)
	}

	err = confFile.Close()
	if err != nil {
		_ = pool.Terminate(ctx)
		log.Critf("[waitForPostgresAndCreateConfig] confFile.Close failed: %v", err)
	}

	cleanerFunc := func() {
		// Terminate the container
		err := pool.Terminate(ctx)
		if err != nil {
			log.Critf("[waitForPostgresAndCreateConfig] pool.Terminate failed: %v", err)
		}

		err = os.Remove(confFile.Name())
		if err != nil {
			log.Critf("[waitForPostgresAndCreateConfig] os.Remove failed: %v", err)
		}
	}

	return confFile.Name(), cleanerFunc
}

func startPostgreSQL(versionPG uint32) (confPath string, cleaner func()) {

	cport := "5432"
	user := "postgres"
	password := "this_is_postgres"
	database := "test_agent2"

	ctx := context.Background()
	req := testcontainers.ContainerRequest{
		Image:        "postgres:" + fmt.Sprint(versionPG),
		ExposedPorts: []string{cport + "/tcp"},
		Env: map[string]string{
			"POSTGRES_USER":     user,
			"POSTGRES_PASSWORD": password,
			"POSTGRES_DB":       database,
		},
	}

	pg, err := testcontainers.GenericContainer(ctx, testcontainers.GenericContainerRequest{
		ContainerRequest: req,
		Started:          true,
	})
	if err != nil {
		log.Critf("[startPostgreSQL] GenericContainer failed: %v", err)
	}

	ip, err := pg.Host(ctx)
	if err != nil {
		log.Critf("[startPostgreSQL] pg.Host failed: %v", err)
	}

	port, err := pg.MappedPort(ctx, nat.Port(cport))
	if err != nil {
		log.Critf("[startPostgreSQL] pg.MappedPort failed: %v", err)
	}

	log.Infof(fmt.Sprintf("postgres://%s:%s@%s:%s/%s?sslmode=disable",
		user, password, ip, port.Port(), database))

	connString := "postgres://postgres:this_is_postgres@" + ip + ":" + port.Port() + "/test_agent2"
	return waitForPostgresAndCreateConfig(pg, ctx, connString)
}
