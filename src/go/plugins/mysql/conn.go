/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
	"context"
	"crypto/tls"
	"database/sql"
	"fmt"
	"strings"
	"sync"
	"time"

	"github.com/go-sql-driver/mysql"
	"github.com/omeid/go-yarn"

	"git.zabbix.com/ap/plugin-support/metric"
	"git.zabbix.com/ap/plugin-support/tlsconfig"
	"git.zabbix.com/ap/plugin-support/uri"

	"git.zabbix.com/ap/plugin-support/log"
	"git.zabbix.com/ap/plugin-support/zbxerr"
)

const (
	// connType
	disable    = "disabled"
	require    = "required"
	verifyCa   = "verify_ca"
	verifyFull = "verify_full"
)

type MyClient interface {
	Query(ctx context.Context, query string, args ...interface{}) (rows *sql.Rows, err error)
	QueryByName(ctx context.Context, queryName string, args ...interface{}) (rows *sql.Rows, err error)
	QueryRow(ctx context.Context, query string, args ...interface{}) (row *sql.Row, err error)
}

type MyConn struct {
	client         *sql.DB
	lastTimeAccess time.Time
	queryStorage   *yarn.Yarn
}

// Query wraps DB.QueryRowContext.
func (conn *MyConn) Query(ctx context.Context, query string, args ...interface{}) (rows *sql.Rows, err error) {
	rows, err = conn.client.QueryContext(ctx, query, args...)

	if ctxErr := ctx.Err(); ctxErr != nil {
		err = ctxErr
	}

	return
}

// QueryByName wraps DB.QueryRowContext.
func (conn *MyConn) QueryByName(ctx context.Context, name string, args ...interface{}) (rows *sql.Rows, err error) {
	if sql, ok := (*conn.queryStorage).Get(name + sqlExt); ok {
		normalizedSQL := strings.TrimRight(strings.TrimSpace(sql), ";")

		return conn.Query(ctx, normalizedSQL, args...)
	}

	return nil, fmt.Errorf("query %s not found", name)
}

// QueryRow wraps DB.QueryRowContext.
func (conn *MyConn) QueryRow(ctx context.Context, query string, args ...interface{}) (row *sql.Row, err error) {
	row = conn.client.QueryRowContext(ctx, query, args...)

	if ctxErr := ctx.Err(); ctxErr != nil {
		err = ctxErr
	}

	return
}

// updateAccessTime updates the last time a connection was accessed.
func (conn *MyConn) updateAccessTime() {
	conn.lastTimeAccess = time.Now()
}

// ConnManager is thread-safe structure for manage connections.
type ConnManager struct {
	sync.Mutex
	connMutex      sync.Mutex
	connections    map[uri.URI]*MyConn
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
		connections:    make(map[uri.URI]*MyConn),
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
			log.Debugf("[%s] Closed unused connection: %s", pluginName, uri.Addr())
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
func (c *ConnManager) create(uri uri.URI, details tlsconfig.Details) (*MyConn, error) {
	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	if _, ok := c.connections[uri]; ok {
		// Should never happen.
		panic("connection already exists")
	}

	config := mysql.NewConfig()
	config.User = uri.User()
	config.Passwd = uri.Password()
	config.Net = uri.Scheme()
	config.Addr = uri.Addr()
	config.Timeout = c.connectTimeout
	config.ReadTimeout = c.callTimeout
	config.InterpolateParams = true

	if err := registerTLSConfig(uri.String(), config, details); err != nil {
		return nil, err
	}

	connector, err := mysql.NewConnector(config)

	if err != nil {
		return nil, err
	}

	client := sql.OpenDB(connector)

	c.connections[uri] = &MyConn{
		client:         client,
		lastTimeAccess: time.Now(),
		queryStorage:   &c.queryStorage,
	}

	log.Debugf("[%s] Created new connection: %s", pluginName, uri.Addr())

	return c.connections[uri], nil
}

func registerTLSConfig(uri string, mysqlConf *mysql.Config, details tlsconfig.Details) error {
	var tlsConf *tls.Config
	var err error

	switch details.TlsConnect {
	case "required":
		tlsConf = &tls.Config{InsecureSkipVerify: true}
	case "verify_ca":
		tlsConf, err = details.GetTLSConfig(true)
		if err != nil {
			return err
		}

		tlsConf.VerifyPeerCertificate = tlsconfig.GetCertificateVerification("", tlsConf.RootCAs)
	case "verify_full":
		tlsConf, err = details.GetTLSConfig(false)
		if err != nil {
			return err
		}

		tlsConf.VerifyPeerCertificate = tlsconfig.GetCertificateVerification(tlsConf.ServerName, tlsConf.RootCAs)
	default:
		return nil
	}

	err = mysql.RegisterTLSConfig(uri, tlsConf)
	if err != nil {
		return err
	}

	mysqlConf.TLSConfig = uri

	return nil
}

// get returns a connection with given uri if it exists and also updates lastTimeAccess, otherwise returns nil.
func (c *ConnManager) get(uri uri.URI) *MyConn {
	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	if conn, ok := c.connections[uri]; ok {
		conn.updateAccessTime()
		return conn
	}

	return nil
}

// GetConnection returns an existing connection or creates a new one.
func (c *ConnManager) GetConnection(uri uri.URI, params map[string]string) (*MyConn, error) {
	c.Lock()
	defer c.Unlock()

	conn := c.get(uri)
	if conn != nil {
		return conn, nil
	}

	details, err := getTlsDetails(params)
	if err != nil {
		return nil, err
	}

	return c.create(uri, details)
}

func getTlsDetails(params map[string]string) (tlsconfig.Details, error) {
	var details tlsconfig.Details
	var err error

	validateCA := true
	validateClient := false
	tlsType := params[tlsConnectParam]

	if tlsType == "" {
		tlsType = disable
	}

	details = tlsconfig.NewDetails(
		params[metric.SessionParam],
		tlsType,
		params[tlsCAParam],
		params[tlsCertParam],
		params[tlsKeyParam],
		params[uriParam],
		disable,
		require,
		verifyCa,
		verifyFull,
	)

	if tlsType == disable || tlsType == require {
		validateCA = false
	}

	if details.TlsKeyFile != "" || details.TlsCertFile != "" {
		validateClient = true
	}

	err = details.Validate(validateCA, validateClient, validateClient)
	if err != nil {
		return details, zbxerr.ErrorInvalidConfiguration.Wrap(err)
	}

	return details, nil
}
