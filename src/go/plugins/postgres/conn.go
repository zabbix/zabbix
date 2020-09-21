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
	"strings"
	"sync"
	"time"

	"github.com/jackc/pgx/v4"
	"github.com/jackc/pgx/v4/pgxpool"
	"github.com/omeid/go-yarn"
	"zabbix.com/pkg/log"
)

var errorQueryNotFound = "query %q not found"

type PostgresClient interface {
	PostgresVersion() int
	QueryRow(ctx context.Context, query string, args ...interface{}) (row pgx.Row, err error)
	QueryRowByName(ctx context.Context, queryName string, args ...interface{}) (row pgx.Row, err error)
	Query(ctx context.Context, query string, args ...interface{}) (row pgx.Rows, err error)
	QueryByName(ctx context.Context, queryName string, args ...interface{}) (row pgx.Rows, err error)
}

// PostgresConn holds pointer to the Pool of Postgres Instance
type PostgresConn struct {
	sync.Mutex
	postgresPool   *pgxpool.Pool
	timeout        time.Duration
	ctx            context.Context
	lastTimeAccess time.Time
	version        int
	connString     string
	queryStorage   *yarn.Yarn
}

// PostgresVersion returns a current username.
func (conn *PostgresConn) PostgresVersion() int {
	return conn.version
}

// QueryRow wraps pgxpool.QueryRow.
func (conn *PostgresConn) QueryRow(ctx context.Context, query string, args ...interface{}) (row pgx.Row, err error) {
	row = conn.postgresPool.QueryRow(ctx, query, args...)

	if ctxErr := ctx.Err(); ctxErr != nil {
		return row, errorTimeout
	}

	return
}

// QueryRowByName executes a query from queryStorage by its name and returns a singe row.
func (conn *PostgresConn) QueryRowByName(ctx context.Context, queryName string, args ...interface{}) (row pgx.Row, err error) {

	if sql, ok := (*conn.queryStorage).Get(queryName + sqlExt); ok {
		normalizedSQL := strings.TrimRight(strings.TrimSpace(sql), ";")

		return conn.QueryRow(ctx, normalizedSQL, args...)
	}

	return nil, fmt.Errorf(errorQueryNotFound, queryName)
}

// Query wraps pgxpool.Query.
func (conn *PostgresConn) Query(ctx context.Context, query string, args ...interface{}) (rows pgx.Rows, err error) {
	rows, err = conn.postgresPool.Query(ctx, query, args...)

	if ctxErr := ctx.Err(); ctxErr != nil {
		return rows, errorTimeout
	}
	return
}

// QueryByName executes a query from queryStorage by its name and returns a singe row.
func (conn *PostgresConn) QueryByName(ctx context.Context, queryName string, args ...interface{}) (rows pgx.Rows, err error) {

	if sql, ok := (*conn.queryStorage).Get(queryName + sqlExt); ok {
		normalizedSQL := strings.TrimRight(strings.TrimSpace(sql), ";")
		return conn.Query(ctx, normalizedSQL, args...)
	}

	return nil, fmt.Errorf(errorQueryNotFound, queryName)
}

// UpdateAccessTime updates the last time postgresCon was accessed.
func (conn *PostgresConn) updateAccessTime() {
	conn.lastTimeAccess = time.Now()
}

func (conn *PostgresConn) finalize() (err error) {
	conn.Lock()
	defer conn.Unlock()
	if conn.postgresPool != nil {
		return
	}

	// get conn pool using url created in postgres.go
	config, err := pgxpool.ParseConfig(conn.connString)
	if err != nil {
		return sanitizeError(err.Error(), conn.connString)
	}

	config.ConnConfig.DialFunc = func(ctx context.Context, network, addr string) (net.Conn, error) {
		d := net.Dialer{}
		newCtx, cancel := context.WithTimeout(context.Background(), conn.timeout)
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

	conn.version = version
	conn.postgresPool = newConn
	conn.ctx = context.Background()
	return
}

func (conn *PostgresConn) close() {
	conn.Lock()
	defer conn.Unlock()
	if conn.postgresPool != nil {
		conn.postgresPool.Close()
		conn.postgresPool = nil
	}
}

// ConnManager is a thread-safe structure for manage connections.
type ConnManager struct {
	sync.Mutex
	connMutex      sync.Mutex
	connections    map[string]*PostgresConn
	keepAlive      time.Duration
	connectTimeout time.Duration
	callTimeout    time.Duration
	Destroy        context.CancelFunc
	queryStorage   yarn.Yarn
	log            log.Logger
}

// NewConnManager initializes connManager structure and runs Go Routine that watches for unused connections.
func (p *Plugin) NewConnManager(keepAlive, connectTimeout, callTimeout, hkInterval time.Duration, queryStorage yarn.Yarn) *ConnManager {
	ctx, cancel := context.WithCancel(context.Background())

	connMgr := &ConnManager{
		connections:    make(map[string]*PostgresConn),
		keepAlive:      keepAlive,
		connectTimeout: connectTimeout,
		callTimeout:    callTimeout,
		Destroy:        cancel, // Destroy stops originated goroutines and closes connections.
		queryStorage:   queryStorage,
		log:            p,
	}

	// Repeatedly check for unused connections and close them
	go connMgr.housekeeper(ctx, hkInterval)
	return connMgr
}

// housekeeper repeatedly checks for unused connections and closes them.
func (c *ConnManager) housekeeper(ctx context.Context, interval time.Duration) {
	ticker := time.NewTicker(interval)

	for {
		select {
		case <-ctx.Done():
			ticker.Stop()
			c.closeAll()

			return
		case <-ticker.C:
			c.closeUnused()
		}
	}
}

// closeAll closes all existed connections.
func (c *ConnManager) closeAll() {
	c.connMutex.Lock()
	for uri, conn := range c.connections {
		conn.postgresPool.Close()
		delete(c.connections, uri)
	}
	c.connMutex.Unlock()
}

// get returns a connection with given id if it exists and also updates lastTimeAccess, otherwise returns nil.
func (c *ConnManager) get(connString string) *PostgresConn {
	c.Lock()
	defer c.Unlock()
	conn, ok := c.connections[connString]
	if !ok {
		conn = &PostgresConn{connString: connString, timeout: c.connectTimeout, queryStorage: &c.queryStorage}
		c.connections[connString] = conn
		c.log.Debugf("created new connection %s", connString)
	}
	conn.updateAccessTime()
	return conn
}

// closeUnused closes each connection that has not been accessed within at least the keepalive interval.
func (c *ConnManager) closeUnused() (err error) {
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
func (c *ConnManager) GetPostgresConnection(connString string) (conn *PostgresConn, err error) {
	conn = c.get(connString)
	if err = conn.finalize(); err != nil {
		c.Lock()
		defer c.Unlock()
		delete(c.connections, connString)
		c.log.Debugf("removed failed connection %s: %s", connString, err)
		return nil, fmt.Errorf("Cannot establish connection to Postgres server: %s", err)
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
