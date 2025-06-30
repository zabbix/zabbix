/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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

package dbconn

import (
	"context"
	"database/sql"
	"database/sql/driver"
	"fmt"
	"net/url"
	"strings"
	"sync"
	"time"

	"github.com/godror/godror"
	"github.com/omeid/go-yarn"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	sysdbaExtension  = " as sysdba"
	sysasmExtension  = " as sysoper"
	sysoperExtension = " as sysasm"

	sysdbaPrivilege  = "sysdba"
	sysasmPrivilege  = "sysoper"
	sysoperPrivilege = "sysasm"
)

var (
	_ OraClient = (*OraConn)(nil)

	errInvalidPrivilege = errs.New("invalid connection privilege")

	errorQueryNotFound = "query %q not found" //nolint:gochecknoglobals
)

// OraClient interface specifies functions to be called to Oracle service. Different implementations
// for plugin and testing functions.
type OraClient interface {
	Query(ctx context.Context, query string, args ...any) (rows *sql.Rows, err error)
	QueryByName(ctx context.Context, queryName string, args ...any) (rows *sql.Rows, err error)
	QueryRow(ctx context.Context, query string, args ...any) (row *sql.Row, err error)
	QueryRowByName(ctx context.Context, queryName string, args ...any) (row *sql.Row, err error)
	WhoAmI() string
}

// OraConn type contains godror oracle connection.
type OraConn struct {
	Client           *sql.DB
	callTimeout      time.Duration
	version          godror.VersionInfo
	lastAccessTime   time.Time
	lastAccessTimeMu sync.Mutex
	ctx              context.Context //nolint:containedctx
	queryStorage     *yarn.Yarn
	username         string
}

// GetConnParams function creates godror connection params and assigns privilege by string passed as arg.
func GetConnParams(privilege string) (godror.ConnParams, error) {
	var out godror.ConnParams

	switch privilege {
	case "sysdba":
		out.IsSysDBA = true
	case "sysoper":
		out.IsSysOper = true
	case "sysasm":
		out.IsSysASM = true
	case "":
	default:
		return godror.ConnParams{},
			errs.Wrapf(errInvalidPrivilege, "unknown Privilege %s", privilege)
	}

	return out, nil
}

// SplitUserPrivilege function gets the user and privilege from the params and splits.
func SplitUserPrivilege(params map[string]string) (user, privilege string, err error) { //nolint:nonamedreturns
	var ok bool

	user, ok = params["User"]
	if !ok {
		return "", "", errs.Wrap(zbxerr.ErrorTooFewParameters, "missing parameter User")
	}

	var extension string

	switch {
	case strings.HasSuffix(strings.ToLower(user), sysdbaExtension):
		privilege = sysdbaPrivilege
		extension = sysdbaExtension
	case strings.HasSuffix(strings.ToLower(user), sysoperExtension):
		privilege = sysoperPrivilege
		extension = sysoperExtension
	case strings.HasSuffix(strings.ToLower(user), sysasmExtension):
		privilege = sysasmPrivilege
		extension = sysasmExtension
	}

	return user[:len(user)-len(extension)], privilege, nil
}

// Query wraps DB.QueryContext.
func (conn *OraConn) Query(
	ctx context.Context, query string, args ...any,
) (*sql.Rows, error) {
	rows, err := conn.Client.QueryContext(ctx, query, args...)

	if ctxErr := ctx.Err(); ctxErr != nil {
		err = ctxErr
	}

	return rows, err
}

// QueryByName executes a query from QueryStorage by its name and returns multiple rows.
func (conn *OraConn) QueryByName(
	ctx context.Context, queryName string, args ...any,
) (*sql.Rows, error) {
	if sqlStr, ok := (*conn.queryStorage).Get(queryName + sqlExt); ok {
		normalizedSQL := strings.TrimRight(strings.TrimSpace(sqlStr), ";")

		return conn.Query(ctx, normalizedSQL, args...)
	}

	return nil, fmt.Errorf(errorQueryNotFound, queryName) //nolint:err113
}

// QueryRow wraps DB.QueryRowContext.
func (conn *OraConn) QueryRow(
	ctx context.Context, query string, args ...any,
) (*sql.Row, error) {
	row := conn.Client.QueryRowContext(ctx, query, args...)

	var err error
	if ctxErr := ctx.Err(); ctxErr != nil {
		err = ctxErr
	}

	return row, err
}

// QueryRowByName executes a query from QueryStorage by its name and returns a single row.
func (conn *OraConn) QueryRowByName(ctx context.Context, queryName string,
	args ...any,
) (*sql.Row, error) {
	if sqlStr, ok := (*conn.queryStorage).Get(queryName + sqlExt); ok {
		normalizedSQL := strings.TrimRight(strings.TrimSpace(sqlStr), ";")

		return conn.QueryRow(ctx, normalizedSQL, args...)
	}

	return nil, fmt.Errorf(errorQueryNotFound, queryName) //nolint:err113
}

// WhoAmI returns a current username.
func (conn *OraConn) WhoAmI() string {
	return conn.username
}

// updateAccessTime updates the last time a connection was accessed.
func (conn *OraConn) updateLastAccessTime(accessTime time.Time) {
	conn.lastAccessTimeMu.Lock()
	defer conn.lastAccessTimeMu.Unlock()

	conn.lastAccessTime = accessTime
}

func (conn *OraConn) getLastAccessTime() time.Time {
	conn.lastAccessTimeMu.Lock()
	defer conn.lastAccessTimeMu.Unlock()

	return conn.lastAccessTime
}

func (conn *OraConn) closeWithLog() {
	if conn.Client == nil {
		return
	}

	err := conn.Client.Close()
	if err != nil {
		log.Debugf("Cannot close Oracle connection: %w", err)
	}
}

// createConnector function creates a connection string and godror connection by ConnDetails.
func createConnector(cd *ConnDetails, connectTimeout time.Duration) (driver.Connector, error) {
	service, err := url.QueryUnescape(cd.Uri.GetParam("service"))

	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorInvalidParams) //nolint:wrapcheck
	}

	connectString := fmt.Sprintf(
		`(DESCRIPTION=(ADDRESS=(PROTOCOL=tcp)(HOST=%s)(PORT=%s))`+
			`(CONNECT_DATA=(SERVICE_NAME="%s"))(CONNECT_TIMEOUT=%d)(RETRY_COUNT=0))`,
		cd.Uri.Host(),
		cd.Uri.Port(),
		service,
		connectTimeout/time.Second,
	)

	connParams, err := GetConnParams(cd.Privilege)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorInvalidParams) //nolint:wrapcheck
	}

	connector := godror.NewConnector(
		godror.ConnectionParams{
			StandaloneConnection: true,
			CommonParams: godror.CommonParams{
				Username:      cd.Uri.User(),
				ConnectString: connectString,
				Password:      godror.NewPassword(cd.Uri.Password()),
			},
			ConnParams: connParams,
		},
	)

	return connector, nil
}
