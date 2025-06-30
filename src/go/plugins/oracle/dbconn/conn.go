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

package dbconn

import (
	"context"
	"database/sql"
	"errors"
	"net/http"
	"sync"
	"time"

	"github.com/godror/godror"
	"github.com/omeid/go-yarn"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/uri"
)

const (
	sqlExt     = ".sql"
	hkInterval = 10 * time.Second
)

// URIDefaults variable contains default URI field values.
var (
	URIDefaults = &uri.Defaults{Scheme: "tcp", Port: "1521"} //nolint:gochecknoglobals

	ErrCannotSetConnection = errors.New("cannot set connection")
)

// Options contains parameters for ConnManager.
type Options struct {
	KeepAlive            time.Duration
	ConnectTimeout       time.Duration
	CallTimeout          time.Duration
	CustomQueriesEnabled bool
	CustomQueriesPath    string
}

// ConnManager is the thread-safe structure for managing connections.
type ConnManager struct {
	logr log.Logger
	opt  Options

	// cached connections
	Connections   map[ConnDetails]*OraConn
	connectionsMu sync.Mutex

	QueryStorage yarn.Yarn
	Destroy      context.CancelFunc
}

// ConnDetails type holds connection parameters to be used as a key in ConnManager to find the connection
// for reuse.
type ConnDetails struct {
	Uri       uri.URI //nolint:revive
	Privilege string

	// NoVersionCheck field turns off server version check on connection creation to avoid real connection
	// to the server. Always use value false (default), only unittests can use true.
	NoVersionCheck bool
}

// NewOptions function creates Options struct.
func NewOptions(
	keepAlive time.Duration,
	connectTimeout time.Duration,
	callTimeout time.Duration,
	customQueriesEnabled bool,
	customQueriesPath string,
) Options {
	return Options{
		KeepAlive:            keepAlive,
		ConnectTimeout:       connectTimeout,
		CallTimeout:          callTimeout,
		CustomQueriesEnabled: customQueriesEnabled,
		CustomQueriesPath:    customQueriesPath,
	}
}

// NewConnManager initializes connManager structure and runs goroutine that watches for unused connections.
func NewConnManager(logr log.Logger, opt Options) *ConnManager {
	ctx, cancel := context.WithCancel(context.Background())

	connMgr := &ConnManager{
		logr: logr,
		opt:  opt,

		Connections: make(map[ConnDetails]*OraConn),

		QueryStorage: setCustomQuery(logr, opt.CustomQueriesEnabled, opt.CustomQueriesPath),
		Destroy:      cancel, // Destroy stops originated goroutines and closes connections.
	}

	go connMgr.housekeeper(ctx)

	return connMgr
}

// GetConnection returns an existing connection or creates a new one.
func (c *ConnManager) GetConnection(cd ConnDetails) (*OraConn, error) { //nolint:gocritic
	conn := c.getConn(cd)
	if conn != nil {
		conn.updateLastAccessTime(time.Now())

		return conn, nil
	}

	conn, err := c.create(&cd)
	if err != nil {
		return nil, err
	}

	return c.setConn(cd, conn)
}

// GetContextWithTimeout function returns context with timeout = conn.callTimeout.
func (conn *OraConn) GetContextWithTimeout() (context.Context, context.CancelFunc) {
	return context.WithTimeout(conn.ctx, conn.callTimeout)
}

// closeUnused closes each connection that has not been accessed at least within the KeepAlive interval.
func (c *ConnManager) closeUnused() {
	c.connectionsMu.Lock()
	defer c.connectionsMu.Unlock()

	for cd, conn := range c.Connections {
		if time.Since(conn.getLastAccessTime()) > c.opt.KeepAlive {
			conn.closeWithLog()

			delete(c.Connections, cd)
			log.Debugf("[Oracle] Closed unused connection: %s", cd.Uri.Addr())
		}
	}
}

// closeAll closes all existing connections.
func (c *ConnManager) closeAll() {
	c.connectionsMu.Lock()
	defer c.connectionsMu.Unlock()

	for connDet, conn := range c.Connections {
		conn.closeWithLog()

		delete(c.Connections, connDet)
	}
}

// housekeeper repeatedly checks for unused connections and closes them or closes all
// if context canceled.
func (c *ConnManager) housekeeper(ctx context.Context) {
	ticker := time.NewTicker(hkInterval)

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

// create creates a new connection for given credentials in cd.
func (c *ConnManager) create(cd *ConnDetails) (*OraConn, error) {
	ctx := godror.ContextWithTraceTag(
		context.Background(),
		godror.TraceTag{
			ClientInfo: "zbx_monitor",
			Module:     godror.DriverName,
		},
	)

	connector, err := createConnector(cd, c.opt.ConnectTimeout)
	if err != nil {
		return nil, err
	}

	client := sql.OpenDB(connector)

	var serverVersion godror.VersionInfo

	var errVer error

	if !cd.NoVersionCheck {
		serverVersion, errVer = godror.ServerVersion(ctx, client)
		if errVer != nil {
			return nil, errs.Wrap(errVer, "server version check failed")
		}
	}

	log.Debugf("[Oracle] Created new connection: %s", cd.Uri.Addr())

	return &OraConn{
		Client:         client,
		callTimeout:    c.opt.CallTimeout,
		version:        serverVersion,
		lastAccessTime: time.Now(),
		ctx:            ctx,
		queryStorage:   &c.QueryStorage,
		username:       cd.Uri.User(),
	}, nil
}

// getConn concurrent cached connection getter.
//
// Attempts to retrieve a connection from cache by its ConnDetails.
// Returns nil if no connection associated with the given ConnDetails is found.
func (c *ConnManager) getConn(cd ConnDetails) *OraConn { //nolint:gocritic
	c.connectionsMu.Lock()
	defer c.connectionsMu.Unlock()

	conn, ok := c.Connections[cd]
	if !ok {
		return nil
	}

	return conn
}

// setConn concurrent cached connection setter.
//
// Returns the cached connection. If the provider connection is already present
// in the cache, it is closed.
//
//nolint:gocritic
func (c *ConnManager) setConn(cd ConnDetails, conn *OraConn) (*OraConn, error) {
	if conn == nil {
		return nil, errs.Wrap(ErrCannotSetConnection, "connection must be instantiated")
	}

	c.connectionsMu.Lock()
	defer c.connectionsMu.Unlock()

	existingConn, ok := c.Connections[cd]
	if ok {
		defer conn.closeWithLog()

		log.Debugf("[Oracle] Closed redundant connection: %s", cd.Uri.Addr())

		return existingConn, nil
	}

	c.Connections[cd] = conn

	return conn, nil
}

// setCustomQuery function if enabled, reads the SQLs from a file by path.
func setCustomQuery(logr log.Logger, enabled bool, path string) yarn.Yarn { //nolint:ireturn
	if !enabled {
		return yarn.NewFromMap(map[string]string{})
	}

	queryStorage, err := yarn.New(http.Dir(path), "*"+sqlExt)
	if err != nil {
		logr.Errf(err.Error())
		// create empty storage if error occurred
		return yarn.NewFromMap(map[string]string{})
	}

	return queryStorage
}
