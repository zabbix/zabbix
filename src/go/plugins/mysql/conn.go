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

package mysql

import (
	"database/sql"
	"strings"
	"sync"
	"time"

	"github.com/go-sql-driver/mysql"
)

type dbConn struct {
	connection     *sql.DB
	lastTimeAccess time.Time
}

type dsn = string

// Thread-safe structure for manage connections.
type connManager struct {
	sync.Mutex
	connMutex   sync.Mutex
	connections map[dsn]*dbConn
	keepAlive   time.Duration
	timeout     time.Duration
}

// updateAccessTime updates the last time a connection was accessed.
func (r *dbConn) updateAccessTime() {
	r.lastTimeAccess = time.Now()
}

// NewConnManager initializes connManager structure and runs Go Routine that watches for unused connections.
func newConnManager(keepAlive, timeout time.Duration) *connManager {
	connMgr := &connManager{
		connections: make(map[dsn]*dbConn),
		keepAlive:   keepAlive,
		timeout:     timeout,
	}

	return connMgr
}

// create creates a new connection with a given URI and password.
func (c *connManager) create(mysqlConf *mysql.Config) (*dbConn, error) {

	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	dsn := mysqlConf.FormatDSN()

	if _, ok := c.connections[dsn]; ok {
		// Should never happen.
		panic("connection already exists")
	}

	conn, err := sql.Open("mysql", dsn)
	if err != nil {
		return nil, err
	}

	if err = conn.Ping(); err != nil {
		return nil, err
	}

	c.connections[dsn] = &dbConn{
		connection:     conn,
		lastTimeAccess: time.Now(),
	}
	impl.Debugf("Created new connection: %s", mysqlConf.Addr)

	return c.connections[dsn], nil
}

// get returns a connection with given cid if it exists and also updates lastTimeAccess, otherwise returns nil.
func (c *connManager) get(mysqlConf *mysql.Config) (conn *dbConn, err error) {

	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	if conn, ok := c.connections[mysqlConf.FormatDSN()]; ok {
		conn.updateAccessTime()
		return conn, nil
	}

	return nil, errorConnectionNotFound
}

func (c *connManager) closeAllConn() (err error) {
	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	for dsn, conn := range c.connections {
		if err = conn.connection.Close(); err == nil {
			delete(c.connections, dsn)
			host, _ := mysql.ParseDSN(dsn)
			impl.Debugf("Closed the connection: %s", host.Addr)
		}
	}

	// Return the last error only.
	return
}

// CloseUnused closes each connection that has not been accessed at least within the keepalive interval.
func (c *connManager) closeUnused() (err error) {

	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	for dsn, conn := range c.connections {
		if time.Since(conn.lastTimeAccess) > c.keepAlive {
			if err = conn.connection.Close(); err == nil {
				delete(c.connections, dsn)
				host, _ := mysql.ParseDSN(dsn)
				impl.Debugf("Closed the unused connection: %s", host.Addr)
			}
		}
	}

	// Return the last error only.
	return
}

func (c *connManager) delete(mysqlConf *mysql.Config) (err error) {

	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	dsn := mysqlConf.FormatDSN()

	if conn, ok := c.connections[dsn]; ok {
		if err = conn.connection.Close(); err == nil {
			delete(c.connections, dsn)
			host, _ := mysql.ParseDSN(dsn)
			impl.Debugf("Closed the killed connection: %s", host.Addr)
		}
	}

	return
}

// GetConnection returns an existing connection or creates a new one.
func (c *connManager) GetConnection(mysqlConf *mysql.Config) (conn *dbConn, err error) {

	c.Lock()
	defer c.Unlock()

	conn, err = c.get(mysqlConf)

	if err != nil {
		conn, err = c.create(mysqlConf)
	} else {
		if err = conn.connection.Ping(); err != nil {
			if strings.Contains(err.Error(), "Connection was killed") {
				if c.delete(mysqlConf) == nil {
					err = errorConnectionKilled
				}
			}
			return nil, err
		}
	}

	return
}
