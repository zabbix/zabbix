/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
	"crypto/x509"
	"database/sql"
	"errors"
	"io/ioutil"
	"sync"
	"time"

	"github.com/go-sql-driver/mysql"

	"zabbix.com/pkg/uri"

	"zabbix.com/pkg/log"
	"zabbix.com/pkg/zbxerr"
)

type MyClient interface {
	Query(ctx context.Context, query string, args ...interface{}) (rows *sql.Rows, err error)
	QueryRow(ctx context.Context, query string, args ...interface{}) (row *sql.Row, err error)
}

type MyConn struct {
	client         *sql.DB
	lastTimeAccess time.Time
}

type tlsDetails struct {
	sessionName string
	tlsConnect  string
	tlsCaFile   string
	tlsCertFile string
	tlsKeyFile  string
	rawUri      string
}

func (conn *MyConn) Query(ctx context.Context, query string, args ...interface{}) (rows *sql.Rows, err error) {
	rows, err = conn.client.QueryContext(ctx, query, args...)

	if ctxErr := ctx.Err(); ctxErr != nil {
		err = ctxErr
	}

	return
}

// Query wraps DB.QueryRowContext.
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
}

// NewConnManager initializes connManager structure and runs Go Routine that watches for unused connections.
func NewConnManager(keepAlive, connectTimeout, callTimeout,
	hkInterval time.Duration) *ConnManager {
	ctx, cancel := context.WithCancel(context.Background())

	connMgr := &ConnManager{
		connections:    make(map[uri.URI]*MyConn),
		keepAlive:      keepAlive,
		connectTimeout: connectTimeout,
		callTimeout:    callTimeout,
		Destroy:        cancel, // Destroy stops originated goroutines and closes connections.
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
func (c *ConnManager) create(uri uri.URI, details tlsDetails) (*MyConn, error) {
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

	if err := registerTLSConfig(config, details); err != nil {
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
	}

	log.Debugf("[%s] Created new connection: %s", pluginName, uri.Addr())

	return c.connections[uri], nil
}

func registerTLSConfig(config *mysql.Config, details tlsDetails) error {
	switch details.tlsConnect {
	case "required":
		mysql.RegisterTLSConfig(details.sessionName, &tls.Config{InsecureSkipVerify: true})
	case "verify_ca":
		conf, err := createTlsConfig(details, true)
		if err != nil {
			return err
		}

		mysql.RegisterTLSConfig(details.sessionName, conf)
	case "verify_full":
		conf, err := createTlsConfig(details, false)
		if err != nil {
			return err
		}

		mysql.RegisterTLSConfig(details.sessionName, conf)
	default:
		return nil
	}

	config.TLSConfig = details.sessionName
	return nil
}

func createTlsConfig(details tlsDetails, skipVerify bool) (*tls.Config, error) {
	rootCertPool := x509.NewCertPool()
	pem, err := ioutil.ReadFile(details.tlsCaFile)
	if err != nil {
		return nil, err
	}

	if ok := rootCertPool.AppendCertsFromPEM(pem); !ok {
		return nil, errors.New("Failed to append PEM")
	}

	clientCerts := make([]tls.Certificate, 0, 1)
	certs, err := tls.LoadX509KeyPair(details.tlsCertFile, details.tlsKeyFile)
	if err != nil {
		return nil, err
	}

	clientCerts = append(clientCerts, certs)

	if skipVerify {
		return &tls.Config{RootCAs: rootCertPool, Certificates: clientCerts, InsecureSkipVerify: skipVerify}, nil
	}

	return &tls.Config{RootCAs: rootCertPool, Certificates: clientCerts, InsecureSkipVerify: skipVerify, ServerName: details.rawUri}, nil
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
func (c *ConnManager) GetConnection(uri uri.URI, details tlsDetails) (conn *MyConn, err error) {
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

func newTlsDetails(session, dbConnect, caFile, certFile, keyFile, uri string) tlsDetails {
	//TODO add validation here
	return tlsDetails{session, dbConnect, caFile, certFile, keyFile, uri}
}
