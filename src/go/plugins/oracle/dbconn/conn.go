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
	"net"
	"net/http"
	"net/url"
	"strings"
	"sync"
	"time"

	"github.com/godror/godror"
	"github.com/godror/godror/dsn"
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

//nolint:gochecknoglobals
var validAdminRoles = map[dsn.AdminRole]bool{
	dsn.SysDBA:    true,
	dsn.SysOPER:   true,
	dsn.SysBACKUP: true,
	dsn.SysDG:     true,
	dsn.SysKM:     true,
	dsn.SysRAC:    true,
	dsn.SysASM:    true,
	dsn.NoRole:    true,
}

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

type versionCheckFunc func(context.Context, godror.Execer) (godror.VersionInfo, error)

// ConnManager is the thread-safe structure for managing connections.
type ConnManager struct {
	logr log.Logger
	Opt  *Options

	// cached connections
	Connections   map[ConnDetails]*OraConn
	connectionsMu sync.Mutex

	QueryStorage yarn.Yarn
	Destroy      context.CancelFunc

	versionCheckF versionCheckFunc
}

// ConnDetails type holds connection parameters to be used as a key in ConnManager to find the connection
// for reuse.
type ConnDetails struct {
	Uri       uri.URI //nolint:revive
	Privilege dsn.AdminRole

	// OnlyHostname is used to distinguish the case when a user wants to connect by URI or by TNS names key.
	// If true - no schema and/or port was specified and, in case if the option ResolveTNS=true, we treat
	// it as hostname and prepare the appropriate connect-string.
	// If false - and ResolveTNS = true, we treat it as tns and prepare tns-like connect-string. See the function
	// isOnlyHostnameOrIP().
	OnlyHostname bool
}

// NewConnDetails function creates a ConnDetails instance for connection to Oracle.
func NewConnDetails(uriStr, user, pwd, service string) (*ConnDetails, error) {
	userSplit, privilegeAdminRole, err := SplitUserAndPrivilege(user)
	if err != nil {
		return nil, errs.WrapConst(err, ErrNewConnDetails)
	}

	service = url.QueryEscape(service)

	onlyHostname, err := isOnlyHostnameOrIP(uriStr)
	if err != nil {
		return nil, errs.WrapConst(err, ErrNewConnDetails)
	}

	// Create uri with attributes as well as default values
	u, err := uri.NewWithCreds(uriStr+"?service="+service, userSplit, pwd, URIDefaults)
	if err != nil {
		return nil, errs.WrapConst(err, ErrNewConnDetails)
	}

	return &ConnDetails{Uri: *u, Privilege: privilegeAdminRole, OnlyHostname: onlyHostname}, nil
}

// NewConnManager initializes connManager structure and runs goroutine that watches for unused connections.
func NewConnManager(logr log.Logger, opt *Options) *ConnManager {
	ctx, cancel := context.WithCancel(context.Background())

	connMgr := &ConnManager{
		logr: logr,
		Opt:  opt,

		Connections: make(map[ConnDetails]*OraConn),

		QueryStorage:  setCustomQuery(logr, opt.CustomQueriesEnabled, opt.CustomQueriesPath),
		Destroy:       cancel, // Destroy stops originated goroutines and closes connections.
		versionCheckF: godror.ServerVersion,
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

// GetContextWithTimeout function returns context with specified timeout.
func (conn *OraConn) GetContextWithTimeout(timeout time.Duration) (context.Context, context.CancelFunc) {
	return context.WithTimeout(conn.ctx, timeout)
}

// GetContextWithCallTimeout function returns context with timeout = conn.callTimeout.
func (conn *OraConn) GetContextWithCallTimeout() (context.Context, context.CancelFunc) {
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

	if c.versionCheckF == nil {
		panic("unassigned Oracle server version check function")
	}

	serverVersion, errVer = c.versionCheckF(ctx, client)
	if errVer != nil {
		return nil, errs.Wrap(errVer, "server version check failed")
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

// SplitUserAndPrivilege parses userWithPrivilege that may contain a privilege role.
// It accepts formats like "system", "system as sysdba", or "SYSTEM AS SYSDBA".
// It returns the clean username, a validated dsn.AdminRole, and an error if the
// format is invalid or the role is unknown.
func SplitUserAndPrivilege(userWithPrivilege string) (string, dsn.AdminRole, error) {
	userStr := normalizeSpaces(userWithPrivilege)
	if userStr == "" {
		return "", "", errs.WrapConst(zbxerr.ErrorTooFewParameters, ErrMissingParamUser)
	}
	// strings.Fields is robust against multiple spaces and leading/trailing whitespace.
	parts := strings.Fields(userStr)

	switch len(parts) {
	case 0:
		// The user string was empty or just whitespace.
		return "", dsn.NoRole, nil // returning nil to keep backwards compatible code
	case 1:
		// A simple username with no privilege, e.g., "myuser". This is valid.
		return parts[0], dsn.NoRole, nil
	case 3:
		// Potential "user as privilege" format.
		// Use strings.EqualFold for a case-insensitive "as" check.
		if !strings.EqualFold(parts[1], "as") {
			return userStr, dsn.NoRole, nil // returning given string and no error to keep backwards compatible code
		}

		role := dsn.AdminRole(strings.ToUpper(parts[2]))

		if validAdminRoles[role] {
			return parts[0], role, nil
		}

		return userStr, dsn.NoRole, nil
	}

	// All other formats (e.g., "user sysdba", "user as", "user as sysdba extra") are invalid.
	return "", dsn.NoRole,
		errs.Wrap(errs.New("invalid user format: expected 'user' or 'user as <privilege>', but got :"), userStr)
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

// isOnlyHostnameOrIP function returns true if a user has a schema, port, IP address (or all) specified.
// Returns:
// - true: if no schema and port in rawURIstr and not an IP address.
// - false: if it contains at least one of the above.
func isOnlyHostnameOrIP(rawURIstr string) (bool, error) {
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

	if net.ParseIP(rawURIstr) != nil {
		return true, nil
	}

	return false, nil
}
