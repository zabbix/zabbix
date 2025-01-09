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
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/tlsconfig"
	"golang.zabbix.com/sdk/uri"
	"golang.zabbix.com/sdk/zbxerr"
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
	client           *sql.DB
	lastAccessTime   time.Time
	lastAccessTimeMu sync.Mutex
	queryStorage     *yarn.Yarn
}

// ConnManager is thread-safe structure for manage connections.
type ConnManager struct {
	connectionsMu  sync.Mutex
	connections    map[connKey]*MyConn
	keepAlive      time.Duration
	connectTimeout time.Duration
	callTimeout    time.Duration
	Destroy        context.CancelFunc
	queryStorage   yarn.Yarn
	log            log.Logger
}

type connKey struct {
	uri        uri.URI
	rawUri     string
	tlsConnect string
	tlsCA      string
	tlsCert    string
	tlsKey     string
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

// GetConnection returns an existing connection or creates a new one.
func (c *ConnManager) GetConnection(uri uri.URI, params map[string]string) (*MyConn, error) {
	ck := createConnKey(uri, params)

	conn := c.getConn(ck)
	if conn != nil {
		c.log.Tracef("connection found for host: %s", uri.Host())

		conn.updateLastAccessTime()

		return conn, nil
	}

	c.log.Tracef("creating new connection for host: %s", uri.Host())

	conn, err := c.create(ck)
	if err != nil {
		return nil, err
	}

	return c.setConn(ck, conn), nil
}

// NewConnManager initializes connManager structure and runs Go Routine that watches for unused connections.
func NewConnManager(
	keepAlive, connectTimeout, callTimeout, hkInterval time.Duration, queryStorage yarn.Yarn, logger log.Logger,
) *ConnManager {
	ctx, cancel := context.WithCancel(context.Background())

	connMgr := &ConnManager{
		connections:    make(map[connKey]*MyConn),
		keepAlive:      keepAlive,
		connectTimeout: connectTimeout,
		callTimeout:    callTimeout,
		Destroy:        cancel, // Destroy stops originated goroutines and closes connections.
		queryStorage:   queryStorage,
		log:            logger,
	}

	go connMgr.housekeeper(ctx, hkInterval)

	return connMgr
}

// updateLastAccessTime updates the last time a connection was accessed.
func (conn *MyConn) updateLastAccessTime() {
	conn.lastAccessTimeMu.Lock()
	defer conn.lastAccessTimeMu.Unlock()

	conn.lastAccessTime = time.Now()
}

func (conn *MyConn) getLastAccessTime() time.Time {
	conn.lastAccessTimeMu.Lock()
	defer conn.lastAccessTimeMu.Unlock()

	return conn.lastAccessTime
}

// closeUnused closes each connection that has not been accessed at least within the keepalive interval.
func (c *ConnManager) closeUnused() {
	c.connectionsMu.Lock()
	defer c.connectionsMu.Unlock()

	for ck, conn := range c.connections {
		if time.Since(conn.getLastAccessTime()) > c.keepAlive {
			conn.client.Close()
			delete(c.connections, ck)
			log.Debugf("[%s] Closed unused connection: %s", pluginName, ck.uri.Addr())
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

// create creates a new connection with given credentials.
func (c *ConnManager) create(ck connKey) (*MyConn, error) {
	details, err := getTLSDetails(ck)
	if err != nil {
		return nil, err
	}

	tlsConfig, err := c.getTLSConfig(details)
	if err != nil {
		return nil, err
	}

	config, err := getMySQLConfig(ck.uri, tlsConfig, c.connectTimeout, c.callTimeout)
	if err != nil {
		return nil, err
	}

	connector, err := mysql.NewConnector(config)
	if err != nil {
		return nil, zbxerr.New("failed to create mysql connector").Wrap(err)
	}

	client := sql.OpenDB(connector)

	log.Debugf("[%s] Created new connection: %s", pluginName, ck.uri.Addr())

	return &MyConn{client: client, lastAccessTime: time.Now(), queryStorage: &c.queryStorage}, nil
}

// getConn concurrent connections cache getter.
func (c *ConnManager) getConn(ck connKey) *MyConn { //nolint:gocritic
	c.connectionsMu.Lock()
	defer c.connectionsMu.Unlock()

	conn, ok := c.connections[ck]
	if !ok {
		return nil
	}

	return conn
}

// setConn concurrent connections cache setter.
//
// Returns the cached connection. If the provider connection is already present
// in cache, it is closed.
//
//nolint:gocritic
func (c *ConnManager) setConn(ck connKey, conn *MyConn) *MyConn {
	c.connectionsMu.Lock()
	defer c.connectionsMu.Unlock()

	existingConn, ok := c.connections[ck]
	if ok {
		defer conn.client.Close() //nolint:errcheck

		log.Debugf("[%s] Closed redundant connection: %s", pluginName, ck.uri.Addr())

		return existingConn
	}

	c.connections[ck] = conn

	return conn
}

func getMySQLConfig(uri uri.URI, tls *tls.Config, connectTimeout, callTimeout time.Duration) (*mysql.Config, error) {
	config := mysql.NewConfig()
	config.User = uri.User()
	config.Passwd = uri.Password()
	config.Net = uri.Scheme()
	config.Addr = uri.Addr()
	config.Timeout = connectTimeout
	config.ReadTimeout = callTimeout
	config.InterpolateParams = true

	if tls == nil {
		return config, nil
	}

	err := mysql.RegisterTLSConfig(uri.String(), tls)
	if err != nil {
		return nil, zbxerr.New("failed to register TLS config").Wrap(err)
	}

	config.TLSConfig = uri.String()

	return config, nil
}

func (c *ConnManager) getTLSConfig(details *tlsconfig.Details) (*tls.Config, error) {
	var (
		tlsConf *tls.Config
		err     error
	)

	switch details.TlsConnect {
	case "required":
		tlsConf, err = c.getRequiredTLSConfig(details)
		if err != nil {
			return nil, zbxerr.New("failed to get TLS config for required connection").Wrap(err)
		}
	case "verify_ca":
		tlsConf, err = details.GetTLSConfig(true)
		if err != nil {
			return nil, zbxerr.New("failed to get TLS config for verify_ca connection").Wrap(err)
		}

		tlsConf.VerifyPeerCertificate = tlsconfig.VerifyPeerCertificateFunc("", tlsConf.RootCAs)
	case "verify_full":
		tlsConf, err = details.GetTLSConfig(false)
		if err != nil {
			return nil, zbxerr.New("failed to get TLS config for verify_full connection").Wrap(err)
		}

		tlsConf.VerifyPeerCertificate = tlsconfig.VerifyPeerCertificateFunc(tlsConf.ServerName, tlsConf.RootCAs)
	default:
		return nil, nil
	}

	return tlsConf, nil
}

func (c *ConnManager) getRequiredTLSConfig(details *tlsconfig.Details) (*tls.Config, error) {
	if details.TlsCaFile != "" {
		c.log.Warningf("server CA will not be verified for %s", details.TlsConnect)
	}

	clientCerts, err := details.LoadCertificates()
	if err != nil {
		return nil, err
	}

	return &tls.Config{Certificates: clientCerts, InsecureSkipVerify: true}, nil
}

func createConnKey(uri uri.URI, params map[string]string) connKey {
	tlsType := params[tlsConnectParam]
	if tlsType == "" {
		tlsType = disable
	}

	return connKey{
		uri:        uri,
		rawUri:     params[uriParam],
		tlsConnect: tlsType,
		tlsCA:      params[tlsCAParam],
		tlsCert:    params[tlsCertParam],
		tlsKey:     params[tlsKeyParam],
	}
}

func getTLSDetails(ck connKey) (*tlsconfig.Details, error) {
	var (
		validateCA     = true
		validateClient = false
	)

	details := tlsconfig.NewDetails(
		"",
		ck.tlsConnect,
		ck.tlsCA,
		ck.tlsCert,
		ck.tlsKey,
		ck.rawUri,
		disable,
		require,
		verifyCa,
		verifyFull,
	)

	if ck.tlsConnect == disable || ck.tlsConnect == require {
		validateCA = false
	}

	if details.TlsKeyFile != "" || details.TlsCertFile != "" {
		validateClient = true
	}

	err := details.Validate(validateCA, validateClient, validateClient)
	if err != nil {
		return nil, zbxerr.ErrorInvalidConfiguration.Wrap(err)
	}

	return &details, nil
}
