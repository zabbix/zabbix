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
	"database/sql/driver"
	"errors"
	"fmt"
	"net/url"
	"regexp"
	"strings"
	"sync"
	"time"

	"github.com/godror/godror"
	"github.com/godror/godror/dsn"
	"github.com/omeid/go-yarn"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	// tnsNone - the plugin won't interpret connString as TNS name.
	tnsNone TNSNameType = iota
	// tnsKey - the plugin will interpret connString as the key of TNS name (if ResolveTNS=true).
	tnsKey
	// tnsValue - the plugin will interpret connString as the value of TNS name.
	tnsValue
)

var (
	_ OraClient = (*OraConn)(nil)

	ErrMissingParamUser = errors.New("missing parameter 'User'") //nolint:revive
	errorQueryNotFound  = "query %q not found"                   //nolint:gochecknoglobals

	userNameRx = regexp.MustCompile(`\s+`)
)

// TNSNameType enumerates different ways to compose connection string depending on the content of ResolveTNS and
// conString.
type TNSNameType int

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

// Query wraps DB.QueryContext.
func (conn *OraConn) Query(
	ctx context.Context, query string, args ...any,
) (*sql.Rows, error) {
	rows, err := conn.Client.QueryContext(ctx, query, args...)
	ctxErr := ctx.Err()

	if ctxErr != nil {
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

	err := ctx.Err()
	if err != nil {
		return nil, errs.Wrap(err, "query context error")
	}

	return row, nil
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

// GetCallTimeout returns callTimeout.
func (conn *OraConn) GetCallTimeout() time.Duration {
	return conn.callTimeout
}

// normalizeSpaces function replaces all whitespace from a username with one whitespace, if any, and
// trims a username.
func normalizeSpaces(s string) string {
	s = strings.TrimSpace(s)

	return userNameRx.ReplaceAllString(s, " ")
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

// createDBConnector function creates a connection string and godror connection by ConnDetails.
func createDBConnector(cd *ConnDetails, connectTimeout time.Duration, resolveTNS bool) (driver.Connector, error) {
	tnsInterpretationType := getTNSType(cd.Uri.Host(), cd.OnlyHostname, resolveTNS)

	connectString, err := prepareConnectString(tnsInterpretationType, cd, connectTimeout)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorInvalidParams)
	}

	connector := createDriverConnector(connectString, cd.Uri.User(), cd.Uri.Password(), cd.Privilege)

	return connector, nil
}

// createDriverConnector function creates a driver.Connector to be used with sql.OpenDB.
func createDriverConnector(hostOrTNS, user, pwd string, privilege dsn.AdminRole) driver.Connector {
	connParams := godror.ConnParams{AdminRole: privilege}

	params := godror.ConnectionParams{}
	params.StandaloneConnection = sql.NullBool{Bool: true, Valid: true}
	params.Username, params.Password = user, godror.NewPassword(pwd)
	params.ConnectString = hostOrTNS
	params.ConnParams = connParams
	params.Timezone = time.Local

	connector := godror.NewConnector(params)

	return connector
}

// getTNSType returns TNSNameType for a host by parameters onlyHostname & resolveTNS.
func getTNSType(host string, onlyHostname, resolveTNS bool) TNSNameType {
	if strings.HasPrefix(strings.TrimSpace(host), "(") {
		return tnsValue
	}

	if !onlyHostname && resolveTNS {
		return tnsKey
	}

	return tnsNone
}

func prepareConnectString(tnsType TNSNameType, cd *ConnDetails, connectTimeout time.Duration) (string, error) {
	service, err := url.QueryUnescape(cd.Uri.GetParam("service"))
	if err != nil {
		return "", err //nolint:wrapcheck
	}

	var connectString string

	switch tnsType {
	case tnsKey, tnsValue:
		connectString = cd.Uri.Host()
	case tnsNone:
		connectString = fmt.Sprintf(
			`(DESCRIPTION=(ADDRESS=(PROTOCOL=tcp)(HOST=%s)(PORT=%s))`+
				`(CONNECT_DATA=(SERVICE_NAME="%s"))(CONNECT_TIMEOUT=%d)(RETRY_COUNT=0))`,
			cd.Uri.Host(),
			cd.Uri.Port(),
			service,
			connectTimeout/time.Second,
		)
	default:
		panic(fmt.Sprintf("unknown TNS interpretation type: %d", tnsType))
	}

	return connectString, nil
}
