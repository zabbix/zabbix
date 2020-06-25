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
	"fmt"
	"net"
	"strconv"
	"sync"
	"time"

	"github.com/jackc/pgx/v4/pgxpool"
	"zabbix.com/pkg/log"
)

// postgresConn holds pointer to the Pool of Postgres Instance
type postgresConn struct {
	sync.Mutex
	postgresPool   *pgxpool.Pool
	lastTimeAccess time.Time
	version        int
	connString     string
	timeout        time.Duration
}

// UpdateAccessTime updates the last time postgresCon was accessed.
func (p *postgresConn) updateAccessTime() {
	p.lastTimeAccess = time.Now()
}

func (p *postgresConn) finalize() (err error) {
	p.Lock()
	defer p.Unlock()
	if p.postgresPool != nil {
		return
	}

	// get conn pool using url created in postgres.go
	config, err := pgxpool.ParseConfig(p.connString)
	if err != nil {
		return sanitizeError(err.Error(), p.connString)
	}
	config.ConnConfig.DialFunc = func(ctx context.Context, network, addr string) (net.Conn, error) {
		d := net.Dialer{}
		newCtx, cancel := context.WithTimeout(context.Background(), p.timeout)
		defer cancel()
		conn, err := d.DialContext(newCtx, network, addr)
		return conn, err
	}

	newConn, err := pgxpool.ConnectConfig(context.Background(), config)
	if err != nil {
		return
	}

	defer func() {
		if err != nil {
			newConn.Close()
		}
	}()

	versionPG, err := GetPostgresVersion(newConn)
	if err != nil {
		return fmt.Errorf("cannot obtain version information: %s", err)
	}

	version, err := strconv.Atoi(versionPG)
	if err != nil {
		return fmt.Errorf("invalid Postgres version: %s", err)
	}

	if version < 100000 {
		return fmt.Errorf("Postgres version %s is not supported", versionPG)
	}

	p.version = version
	p.postgresPool = newConn
	return
}

func (p *postgresConn) close() {
	p.Lock()
	defer p.Unlock()
	if p.postgresPool != nil {
		p.postgresPool.Close()
		p.postgresPool = nil
	}
}

// Thread-safe structure for manage connections.
type connManager struct {
	sync.Mutex
	connections map[string]*postgresConn
	keepAlive   time.Duration
	timeout     time.Duration
	controlSink chan interface{}
	log         log.Logger
}

func (c *connManager) stop() {
	c.controlSink <- nil

	c.Lock()
	defer c.Unlock()

	for _, conn := range c.connections {
		conn.close()
	}
}

// NewConnManager initializes connManager structure and runs Go Routine that watches for unused connections.
func (p *Plugin) NewConnManager(keepAlive, timeout time.Duration) *connManager {
	connMgr := &connManager{
		connections: make(map[string]*postgresConn),
		keepAlive:   keepAlive,
		timeout:     timeout,
		controlSink: make(chan interface{}),
		log:         p,
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
					p.Errf("[%s] Error occurred while closing postgresCon: %s", pluginName, err)
				}
			}
		}
	}()
	return connMgr
}

// get returns a connection with given id if it exists and also updates lastTimeAccess, otherwise returns nil.
func (c *connManager) get(connString string) *postgresConn {
	c.Lock()
	defer c.Unlock()
	conn, ok := c.connections[connString]
	if !ok {
		conn = &postgresConn{connString: connString, timeout: c.timeout}
		c.connections[connString] = conn
		c.log.Debugf("created new connection %s", connString)
	}
	conn.updateAccessTime()
	return conn
}

// closeUnused closes each connection that has not been accessed within at least the keepalive interval.
func (c *connManager) closeUnused() (err error) {
	c.Lock()
	defer c.Unlock()

	for connString, conn := range c.connections {
		if time.Since(conn.lastTimeAccess) > c.keepAlive {
			conn.close()
			delete(c.connections, connString)
			c.log.Debugf("closed unused connection: %s", connString)
		}
	}
	// Return the last error only
	return
}

// GetPostgresConnection returns the existed connection or creates a new one.
func (c *connManager) GetPostgresConnection(connString string) (conn *postgresConn, err error) {
	conn = c.get(connString)
	if err = conn.finalize(); err != nil {
		c.Lock()
		defer c.Unlock()
		delete(c.connections, connString)
		c.log.Debugf("removed failed connection %s: %s", connString, err)
		return nil, fmt.Errorf("Cannot estabilish connection to Postgres server: %s", err)
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
