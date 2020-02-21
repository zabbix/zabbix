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
	"strconv"
	"sync"
	"time"

	"github.com/jackc/pgx/v4/pgxpool"
	"zabbix.com/pkg/log"
)

const poolSize = 10

const clientName = "zbx_monitor"

// postgresConn holds pointer to the Pool of Postgres Instance
type postgresConn struct {
	postgresPool   *pgxpool.Pool
	lastTimeAccess time.Time
	version        string `conf:"default=100006"`
}

// UpdateAccessTime updates the last time postgresCon was accessed.
func (p *postgresConn) UpdateAccessTime() {
	p.lastTimeAccess = time.Now()
}

// Thread-safe structure for manage connections.
type connManager struct {
	sync.Mutex
	connMutex   sync.Mutex
	connections map[string]*postgresConn
	keepAlive   time.Duration
	timeout     time.Duration
	controlSink chan interface{}
}

func (c *connManager) stop() {
	c.controlSink <- nil

	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	for _, conn := range c.connections {
		conn.postgresPool.Close()
	}
}

// NewConnManager initializes connManager structure and runs Go Routine that watches for unused connections.
func (p *Plugin) NewConnManager(keepAlive, timeout time.Duration) *connManager {
	connMgr := &connManager{
		connections: make(map[string]*postgresConn),
		keepAlive:   keepAlive,
		timeout:     timeout,
		controlSink: make(chan interface{}),
	}

	// Repeatedly check for unused connections and close them
	go func() {
		ticker := time.NewTicker(10 * time.Second)
		for {
			select {
			case <-connMgr.controlSink:
				ticker.Stop()
				return
			case <-ticker.C:
				if err := connMgr.closeUnused(); err != nil {
					p.Errf("[%s] Error occurred while closing postgresCon: %s", pluginName, err.Error())
				}
			}
		}
	}()
	return connMgr
}

// create creates a new connection
func (c *connManager) create(connString string) (conn *postgresConn, err error) {

	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	if _, ok := c.connections[connString]; ok {
		// Should never happens
		panic("connection already exists")
	}

	//get conn pool using url created in postgres.go
	config, err := pgxpool.ParseConfig(connString)
	if err != nil {
		log.Errf("[%s] cannot parse config file: %s", connString, err.Error())
		return nil, errorCannotParsePostgresURL
	}

	newConn, err := pgxpool.ConnectConfig(context.Background(), config)
	if err != nil {
		log.Errf("[%s] cannot connect to Postgres using connect string: %s", connString, err.Error())
		return nil, errorCannotConnectPostgres
	}

	versionPG, err := GetPostgresVersion(newConn)
	if err != nil {
		log.Errf("[%s] cannot get Postgres version: %s", connString, err.Error())
		return nil, errorCannotGetPostgresVersion
	}

	version, err := strconv.Atoi(versionPG)
	if err != nil {
		return nil, errorCannotConvertPostgresVersionInt
	}

	if version < 90000 {
		log.Errf("[%s] current PG version is not supported : %s", versionPG, errorUnsupportePostgresVersion)
		return nil, errorUnsupportePostgresVersion
	}

	// save new conn under URL string
	c.connections[connString] = &postgresConn{postgresPool: newConn, lastTimeAccess: time.Now(), version: versionPG}

	log.Debugf("[%s] Created new connection: %s", pluginName, connString)

	return c.connections[connString], nil
}

// get returns a connection with given id if it exists and also updates lastTimeAccess, otherwise returns nil.
func (c *connManager) get(connString string) *postgresConn {

	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	if conn, ok := c.connections[connString]; ok {
		conn.UpdateAccessTime()
		return conn
	}

	return nil
}

// CloseUnused closes each connection that has not been accessed within at least the keepalive interval.
func (c *connManager) closeUnused() (err error) {
	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	for connString, conn := range c.connections {
		if time.Since(conn.lastTimeAccess) > c.keepAlive {
			conn.postgresPool.Close()
			delete(c.connections, connString)
			log.Debugf("[%s] Closed unused connection: %s", pluginName, connString)
		}
	}
	// Return the last error only
	return
}

// GetPostgresConnection returns the existed connection or creates a new one.
func (c *connManager) GetPostgresConnection(connString string) (conn *postgresConn, err error) {
	c.Lock()
	defer c.Unlock()

	conn = c.get(connString)
	if conn == nil {
		conn, err = c.create(connString)
	}

	return
}

// GetPostgresVersion exec query to get PG version from PG we conected to
func GetPostgresVersion(conn *pgxpool.Pool) (versionPG string, err error) {
	err = conn.QueryRow(context.Background(), "select current_setting('server_version_num');").Scan(&versionPG)
	if err != nil {
		return versionPG, err
	}
	return
}
