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
	"context"
	"crypto/tls"
	"database/sql"
	"fmt"
	"net"
	"net/url"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"git.zabbix.com/ap/plugin-support/log"
	"git.zabbix.com/ap/plugin-support/uri"
	"git.zabbix.com/ap/plugin-support/zbxerr"
	"github.com/jackc/pgx/v4/pgxpool"
	"github.com/jackc/pgx/v4/stdlib"
	"github.com/omeid/go-yarn"
	"zabbix.com/pkg/tlsconfig"
)

const MinSupportedPGVersion = 100000

type PostgresClient interface {
	Query(ctx context.Context, query string, args ...interface{}) (rows *sql.Rows, err error)
	QueryByName(ctx context.Context, queryName string, args ...interface{}) (rows *sql.Rows, err error)
	QueryRow(ctx context.Context, query string, args ...interface{}) (row *sql.Row, err error)
	QueryRowByName(ctx context.Context, queryName string, args ...interface{}) (row *sql.Row, err error)
	PostgresVersion() int
}

// PGConn holds pointer to the Pool of Postgres Instance.
type PGConn struct {
	client         *sql.DB
	callTimeout    time.Duration
	ctx            context.Context
	lastTimeAccess time.Time
	version        int
	queryStorage   *yarn.Yarn
	address        string
}

var errorQueryNotFound = "query %q not found"

// Query wraps pgxpool.Query.
func (conn *PGConn) Query(ctx context.Context, query string, args ...interface{}) (rows *sql.Rows, err error) {
	rows, err = conn.client.QueryContext(ctx, query, args...)

	if ctxErr := ctx.Err(); ctxErr != nil {
		err = ctxErr
	}

	return
}

// QueryByName executes a query from queryStorage by its name and returns a single row.
func (conn *PGConn) QueryByName(ctx context.Context, queryName string, args ...interface{}) (rows *sql.Rows, err error) {
	if sql, ok := (*conn.queryStorage).Get(queryName + sqlExt); ok {
		normalizedSQL := strings.TrimRight(strings.TrimSpace(sql), ";")

		return conn.Query(ctx, normalizedSQL, args...)
	}

	return nil, fmt.Errorf(errorQueryNotFound, queryName)
}

// QueryRow wraps pgxpool.QueryRow.
func (conn *PGConn) QueryRow(ctx context.Context, query string, args ...interface{}) (row *sql.Row, err error) {
	row = conn.client.QueryRowContext(ctx, query, args...)

	if ctxErr := ctx.Err(); ctxErr != nil {
		err = ctxErr
	}

	return
}

// QueryRowByName executes a query from queryStorage by its name and returns a single row.
func (conn *PGConn) QueryRowByName(ctx context.Context, queryName string, args ...interface{}) (row *sql.Row, err error) {
	if sql, ok := (*conn.queryStorage).Get(queryName + sqlExt); ok {
		normalizedSQL := strings.TrimRight(strings.TrimSpace(sql), ";")

		return conn.QueryRow(ctx, normalizedSQL, args...)
	}

	return nil, fmt.Errorf(errorQueryNotFound, queryName)
}

// GetPostgresVersion exec SQL query to retrieve the version of PostgreSQL server we are currently connected to.
func getPostgresVersion(ctx context.Context, conn *sql.DB) (version int, err error) {
	err = conn.QueryRowContext(ctx, `select current_setting('server_version_num');`).Scan(&version)

	return
}

// PostgresVersion returns the version of PostgreSQL server we are currently connected to.
func (conn *PGConn) PostgresVersion() int {
	return conn.version
}

// updateAccessTime updates the last time a connection was accessed.
func (conn *PGConn) updateAccessTime() {
	conn.lastTimeAccess = time.Now()
}

// ConnManager is a thread-safe structure for manage connections.
type ConnManager struct {
	sync.Mutex
	connMutex      sync.Mutex
	connections    map[string]*PGConn
	keepAlive      time.Duration
	connectTimeout time.Duration
	callTimeout    time.Duration
	Destroy        context.CancelFunc
	queryStorage   yarn.Yarn
}

// NewConnManager initializes connManager structure and runs Go Routine that watches for unused connections.
func NewConnManager(keepAlive, connectTimeout, callTimeout,
	hkInterval time.Duration, queryStorage yarn.Yarn) *ConnManager {
	ctx, cancel := context.WithCancel(context.Background())

	connMgr := &ConnManager{
		connections:    make(map[string]*PGConn),
		keepAlive:      keepAlive,
		connectTimeout: connectTimeout,
		callTimeout:    callTimeout,
		Destroy:        cancel, // Destroy stops originated goroutines and closes connections.
		queryStorage:   queryStorage,
	}

	go connMgr.housekeeper(ctx, hkInterval)

	return connMgr
}

// closeUnused closes each connection that has not been accessed at least within the keepalive interval.
func (c *ConnManager) closeUnused() {
	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	for uri, conn := range c.connections {
		if time.Since(conn.lastTimeAccess) > c.keepAlive {
			conn.client.Close()
			delete(c.connections, uri)
			log.Debugf("[%s] Closed unused connection: %s", pluginName, conn.address)
		}
	}
}

// closeAll closes all existed connections.
func (c *ConnManager) closeAll() {
	c.connMutex.Lock()
	for uri, conn := range c.connections {
		conn.client.Close()
		delete(c.connections, uri)
	}
	c.connMutex.Unlock()
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

// create creates a new connection with given credentials.
func (c *ConnManager) create(uri uri.URI, details tlsconfig.Details) (*PGConn, error) {
	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	if _, ok := c.connections[uri.NoQueryString()]; ok {
		// Should never happen.
		panic("connection already exists")
	}

	ctx := context.Background()

	host := uri.Host()
	port := uri.Port()

	if uri.Scheme() == "unix" {
		socket := uri.Addr()
		host = filepath.Dir(socket)

		ext := filepath.Ext(filepath.Base(socket))
		if len(ext) <= 1 {
			return nil, fmt.Errorf("incorrect socket: %q", socket)
		}

		port = ext[1:]
	}

	dbname, err := url.QueryUnescape(uri.GetParam("dbname"))
	if err != nil {
		return nil, err
	}

	dsn := fmt.Sprintf("host=%s port=%s dbname=%s user=%s",
		host, port, dbname, uri.User())

	if uri.Password() != "" {
		dsn += " password=" + uri.Password()
	}

	client, err := createTLSClient(dsn, c.connectTimeout, details)
	if err != nil {
		return nil, err
	}

	serverVersion, err := getPostgresVersion(ctx, client)
	if err != nil {
		return nil, err
	}

	if serverVersion < MinSupportedPGVersion {
		return nil, fmt.Errorf("postgres version %d is not supported", serverVersion)
	}

	c.connections[uri.NoQueryString()] = &PGConn{
		client:         client,
		callTimeout:    c.callTimeout,
		version:        serverVersion,
		lastTimeAccess: time.Now(),
		ctx:            ctx,
		queryStorage:   &c.queryStorage,
		address:        uri.Addr(),
	}

	log.Debugf("[%s] Created new connection: %s", pluginName, uri.Addr())

	return c.connections[uri.NoQueryString()], nil
}

func createTLSClient(dsn string, timeout time.Duration, details tlsconfig.Details) (*sql.DB, error) {
	config, err := pgxpool.ParseConfig(dsn)
	if err != nil {
		return nil, err
	}

	config.ConnConfig.DialFunc = func(ctx context.Context, network, addr string) (net.Conn, error) {
		d := net.Dialer{}
		ctxTimeout, cancel := context.WithTimeout(context.Background(), timeout)
		defer cancel()

		conn, err := d.DialContext(ctxTimeout, network, addr)

		return conn, err
	}

	config.ConnConfig.TLSConfig, err = getTLSConfig(details)
	if err != nil {
		return nil, err
	}

	return stdlib.OpenDB(*config.ConnConfig), nil
}

func getTLSConfig(details tlsconfig.Details) (*tls.Config, error) {
	switch details.TlsConnect {
	case "required":
		return &tls.Config{InsecureSkipVerify: true}, nil
	case "verify_ca":
		return tlsconfig.CreateConfig(details, true)
	case "verify_full":
		return tlsconfig.CreateConfig(details, false)
	}

	return nil, nil
}

// get returns a connection with given uri if it exists and also updates lastTimeAccess, otherwise returns nil.
func (c *ConnManager) get(uri uri.URI) *PGConn {
	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	if conn, ok := c.connections[uri.NoQueryString()]; ok {
		conn.updateAccessTime()
		return conn
	}

	return nil
}

// GetConnection returns an existing connection or creates a new one.
func (c *ConnManager) GetConnection(uri uri.URI, details tlsconfig.Details) (conn *PGConn, err error) {
	c.Lock()
	defer c.Unlock()

	conn = c.get(uri)

	if conn == nil {
		conn, err = c.create(uri, details)
	}

	if err != nil {
		err = zbxerr.ErrorConnectionFailed.Wrap(err)
	}

	return
}
