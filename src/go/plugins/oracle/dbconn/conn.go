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
	"net/url"
	"strings"
	"sync"
	"time"

	"github.com/godror/godror"
	"github.com/omeid/go-yarn"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/uri"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	sqlExt     = ".sql"
	hkInterval = 10 * time.Second
)

// URIDefaults variable contains default URI field values.
var (
	URIDefaults = &uri.Defaults{Scheme: "tcp", Port: "1521"} //nolint:gochecknoglobals

	ErrCannotSetConnection = errors.New("cannot set connection")
	ErrNewConnDetails      = errors.New("cannot create connection details")
)

// Options contains parameters for ConnManager.
type Options struct {
	KeepAlive            time.Duration
	ConnectTimeout       time.Duration
	CallTimeout          time.Duration
	CustomQueriesEnabled bool
	CustomQueriesPath    string
	ResolveTNS           bool
}

// ConnManager is the thread-safe structure for managing connections.
type ConnManager struct {
	logr log.Logger
	Opt  *Options

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

	// OnlyHostname is used to distinguish the case when a user wants to connect by URI or by TNS names key.
	// If true - no schema and/or port was specified and, in case if the option ResolveTNS=true, we treat
	// it as hostname and prepare the appropriate connect-string.
	// If false - and ResolveTNS=true we treat it as tns and prepare tns-like connect-string.
	OnlyHostname bool
	// NoVersionCheck field turns off server version check on connection creation to avoid real connection
	// to the server. Always use value false (default), only unittests can use true.
	NoVersionCheck bool
}

// NewConnDetails function creates a ConnDetails instance for connectin to Oracle.
func NewConnDetails(uriStr, user, pwd, service string) (*ConnDetails, error) {
	userSplit, privilegeSplit, err := splitUserPrivilege(user)
	if err != nil {
		return nil, errs.WrapConst(err, ErrNewConnDetails) //nolint:wrapcheck
	}

	service = url.QueryEscape(service)

	onlyHostname, err := containsOnlyHostname(uriStr)
	if err != nil {
		return nil, errs.WrapConst(err, ErrNewConnDetails) //nolint:wrapcheck
	}

	// Create uri with attrinutes as well as default values
	u, err := uri.NewWithCreds(uriStr+"?service="+service, userSplit, pwd, URIDefaults)
	if err != nil {
		return nil, errs.WrapConst(err, ErrNewConnDetails) //nolint:wrapcheck
	}

	return &ConnDetails{Uri: *u, Privilege: privilegeSplit, OnlyHostname: onlyHostname}, nil
}

// NewConnManager initializes connManager structure and runs goroutine that watches for unused connections.
func NewConnManager(logr log.Logger, opt *Options) *ConnManager {
	ctx, cancel := context.WithCancel(context.Background())

	connMgr := &ConnManager{
		logr: logr,
		Opt:  opt,

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

	conn, err := c.createConn(&cd)
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
		if time.Since(conn.getLastAccessTime()) > c.Opt.KeepAlive {
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

// createConn creates a new connection for given credentials in cd.
func (c *ConnManager) createConn(cd *ConnDetails) (*OraConn, error) {
	ctx := godror.ContextWithTraceTag(
		context.Background(),
		godror.TraceTag{
			ClientInfo: "zbx_monitor",
			Module:     godror.DriverName,
		},
	)

	connector, err := createDBConnector(cd, c.Opt.ConnectTimeout, c.Opt.ResolveTNS)
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
		callTimeout:    c.Opt.CallTimeout,
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

// splitUserPrivilege function splits userWithPrivilege into user and privilege.
func splitUserPrivilege(userWithPrivilege string) (user, privilege string, err error) { //nolint:nonamedreturns
	userWithPrivilege = normalizeSpaces(userWithPrivilege)
	if userWithPrivilege == "" {
		return "", "", errs.WrapConst(zbxerr.ErrorTooFewParameters, ErrMissingParamUser) //nolint:wrapcheck
	}

	var extension string

	switch {
	case strings.HasSuffix(strings.ToLower(userWithPrivilege), sysdbaExtension):
		privilege = sysdbaPrivilege
		extension = sysdbaExtension
	case strings.HasSuffix(strings.ToLower(userWithPrivilege), sysoperExtension):
		privilege = sysoperPrivilege
		extension = sysoperExtension
	case strings.HasSuffix(strings.ToLower(userWithPrivilege), sysasmExtension):
		privilege = sysasmPrivilege
		extension = sysasmExtension
	}

	user = userWithPrivilege[:len(userWithPrivilege)-len(extension)]

	return user, privilege, nil
}

// setCustomQuery function if enabled, reads the SQLs from a file by path.
//
//nolint:ireturn
func setCustomQuery(logr log.Logger, enabled bool, path string) yarn.Yarn {
	if !enabled {
		return yarn.NewFromMap(map[string]string{})
	}

	queryStorage, err := yarn.New(http.Dir(path), "*"+sqlExt)
	if err != nil {
		logr.Errf(err.Error())

		// createConn empty storage if error occurred
		return yarn.NewFromMap(map[string]string{})
	}

	return queryStorage
}

// containsOnlyHostname function returns true if a user has a schema, port (or both) specified.
// Returns:
// - true: if no schema and port in rawURIstr.
// - false: if it contains at least one of the above.
func containsOnlyHostname(rawURIstr string) (bool, error) {
	rawURI, err := uri.New(rawURIstr, nil)
	if err != nil {
		return false, errs.Wrap(err, "uri creation failed")
	}

	rawURIstr = strings.TrimSpace(rawURIstr)

	if strings.HasPrefix(rawURIstr, rawURI.Scheme()+"://") {
		return true, nil
	}

	if rawURI.Port() != "" {
		return true, nil
	}

	return false, nil
}
