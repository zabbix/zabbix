/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

package oracle

import (
	"context"
	"database/sql"
	"fmt"
	"net/url"
	"strings"
	"sync"
	"time"

	"github.com/godror/godror"
	"github.com/godror/godror/dsn"
	"github.com/omeid/go-yarn"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/uri"
)

var errorQueryNotFound = "query %q not found"

type OraClient interface {
	Query(ctx context.Context, query string, args ...interface{}) (rows *sql.Rows, err error)
	QueryByName(ctx context.Context, queryName string, args ...interface{}) (rows *sql.Rows, err error)
	QueryRow(ctx context.Context, query string, args ...interface{}) (row *sql.Row, err error)
	QueryRowByName(ctx context.Context, queryName string, args ...interface{}) (row *sql.Row, err error)
	WhoAmI() string
}

type OraConn struct {
	client           *sql.DB
	callTimeout      time.Duration
	version          godror.VersionInfo
	lastAccessTime   time.Time
	lastAccessTimeMu sync.Mutex
	ctx              context.Context //nolint:containedctx
	queryStorage     *yarn.Yarn
	username         string
}

// ConnManager is thread-safe structure for manage connections.
type ConnManager struct {
	// cached connections
	connections   map[connDetails]*OraConn
	connectionsMu sync.Mutex

	keepAlive      time.Duration
	connectTimeout time.Duration
	callTimeout    time.Duration
	Destroy        context.CancelFunc
	queryStorage   yarn.Yarn
}

type connDetails struct {
	uri       uri.URI
	privilege dsn.AdminRole
}

// Query wraps DB.QueryContext.
func (conn *OraConn) Query(ctx context.Context, query string, args ...interface{}) (rows *sql.Rows, err error) {
	rows, err = conn.client.QueryContext(ctx, query, args...)

	if ctxErr := ctx.Err(); ctxErr != nil {
		err = ctxErr
	}

	return
}

// QueryByName executes a query from queryStorage by its name and returns multiple rows.
func (conn *OraConn) QueryByName(ctx context.Context, queryName string,
	args ...interface{},
) (rows *sql.Rows, err error) {
	if sql, ok := (*conn.queryStorage).Get(queryName + sqlExt); ok {
		normalizedSQL := strings.TrimRight(strings.TrimSpace(sql), ";")

		return conn.Query(ctx, normalizedSQL, args...)
	}

	return nil, fmt.Errorf(errorQueryNotFound, queryName)
}

// QueryRow wraps DB.QueryRowContext.
func (conn *OraConn) QueryRow(ctx context.Context, query string, args ...interface{}) (row *sql.Row, err error) {
	row = conn.client.QueryRowContext(ctx, query, args...)

	if ctxErr := ctx.Err(); ctxErr != nil {
		err = ctxErr
	}

	return
}

// QueryRowByName executes a query from queryStorage by its name and returns a single row.
func (conn *OraConn) QueryRowByName(ctx context.Context, queryName string,
	args ...interface{},
) (row *sql.Row, err error) {
	if sql, ok := (*conn.queryStorage).Get(queryName + sqlExt); ok {
		normalizedSQL := strings.TrimRight(strings.TrimSpace(sql), ";")

		return conn.QueryRow(ctx, normalizedSQL, args...)
	}

	return nil, fmt.Errorf(errorQueryNotFound, queryName)
}

// WhoAmI returns a current username.
func (conn *OraConn) WhoAmI() string {
	return conn.username
}

// updateAccessTime updates the last time a connection was accessed.
func (conn *OraConn) updateLastAccessTime() {
	conn.lastAccessTimeMu.Lock()
	defer conn.lastAccessTimeMu.Unlock()

	conn.lastAccessTime = time.Now()
}

func (conn *OraConn) getLastAccessTime() time.Time {
	conn.lastAccessTimeMu.Lock()
	defer conn.lastAccessTimeMu.Unlock()

	return conn.lastAccessTime
}

// NewConnManager initializes connManager structure and runs Go Routine that watches for unused connections.
func NewConnManager(keepAlive, connectTimeout, callTimeout,
	hkInterval time.Duration, queryStorage yarn.Yarn,
) *ConnManager {
	ctx, cancel := context.WithCancel(context.Background())

	connMgr := &ConnManager{
		connections:    make(map[connDetails]*OraConn),
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
	c.connectionsMu.Lock()
	defer c.connectionsMu.Unlock()

	for cd, conn := range c.connections {
		if time.Since(conn.getLastAccessTime()) > c.keepAlive {
			conn.client.Close()
			delete(c.connections, cd)
			log.Debugf("[%s] Closed unused connection: %s", pluginName, cd.uri.Addr())
		}
	}
}

// closeAll closes all existed connections.
func (c *ConnManager) closeAll() {
	c.connectionsMu.Lock()
	defer c.connectionsMu.Unlock()

	for uri, conn := range c.connections {
		conn.client.Close()
		delete(c.connections, uri)
	}
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

// create creates a new connection for given credentials.
func (c *ConnManager) create(cd connDetails) (*OraConn, error) {
	ctx := godror.ContextWithTraceTag(
		context.Background(),
		godror.TraceTag{
			ClientInfo: "zbx_monitor",
			Module:     godror.DriverName,
		},
	)

	service, err := url.QueryUnescape(cd.uri.GetParam("service"))
	if err != nil {
		return nil, err
	}

	connectString := fmt.Sprintf(
		`(DESCRIPTION=(ADDRESS=(PROTOCOL=tcp)(HOST=%s)(PORT=%s))`+
			`(CONNECT_DATA=(SERVICE_NAME="%s"))(CONNECT_TIMEOUT=%d)(RETRY_COUNT=0))`,
		cd.uri.Host(),
		cd.uri.Port(),
		service,
		c.connectTimeout/time.Second,
	)

	connParams := godror.ConnParams{
		AdminRole: cd.privilege,
	}

	var params godror.ConnectionParams
	params.StandaloneConnection = sql.NullBool{Bool: true, Valid: true}
	params.Username, params.Password = cd.uri.User(), godror.NewPassword(cd.uri.Password())
	params.ConnectString = connectString
	params.ConnParams = connParams
	client := sql.OpenDB(godror.NewConnector(params))

	serverVersion, err := godror.ServerVersion(ctx, client)
	if err != nil {
		return nil, err
	}

	log.Debugf("[%s] Created new connection: %s", pluginName, cd.uri.Addr())

	return &OraConn{
		client:         client,
		callTimeout:    c.callTimeout,
		version:        serverVersion,
		lastAccessTime: time.Now(),
		ctx:            ctx,
		queryStorage:   &c.queryStorage,
		username:       cd.uri.User(),
	}, nil
}

// getConn concurrent cached connection getter.
//
// Attempts to retrieve a connection from cache by its connDetails.
// Returns nil if no connection associated with the given connDetails is found.
func (c *ConnManager) getConn(cd connDetails) *OraConn {
	c.connectionsMu.Lock()
	defer c.connectionsMu.Unlock()

	conn, ok := c.connections[cd]
	if !ok {
		return nil
	}

	return conn
}

// setConn concurrent cached connection setter.
//
// Returns the cached connection. If the provider connection is already present
// in cache, it is closed.
func (c *ConnManager) setConn(cd connDetails, conn *OraConn) *OraConn {
	c.connectionsMu.Lock()
	defer c.connectionsMu.Unlock()

	existingConn, ok := c.connections[cd]
	if ok {
		defer conn.client.Close() //nolint:errcheck

		log.Debugf("[%s] Closed redundant connection: %s", pluginName, cd.uri.Addr())

		return existingConn
	}

	c.connections[cd] = conn

	return conn
}

// getConnection returns an existing connection or creates a new one.
func (c *ConnManager) getConnection(cd connDetails) (*OraConn, error) {
	conn := c.getConn(cd)
	if conn != nil {
		conn.updateLastAccessTime()

		return conn, nil
	}

	conn, err := c.create(cd)
	if err != nil {
		return nil, err
	}

	return c.setConn(cd, conn), err
}
