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

package conn

import (
	"context"
	"strconv"
	"sync"
	"time"

	"github.com/ceph/go-ceph/rados"
	"golang.org/x/sync/singleflight"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/uri"
	"golang.zabbix.com/sdk/zbxsync"
)

const housekeeperInterval = 10 * time.Second

// Creating own rados create function to make module more unit testable.
var newRadosConn = func(user string) (radosConnection, error) {
	return rados.NewConnWithUser(user)
}

// We also need to mock the methods on the returned connection,
// so we define an interface for what we use.
type radosConnection interface {
	SetConfigOption(option string, value string) error
	Connect() error
	MonCommand(args []byte) ([]byte, string, error)
	Shutdown()
}

// ConnManager is thread-safe structure for manage connections.
type ConnManager struct {
	connections    *zbxsync.SyncMap[uri.URI, *Conn]
	keepAlive      time.Duration
	connectTimeout int // seconds
	callTimeout    int // seconds
	log            log.Logger
	destroy        context.CancelFunc
	createGroup    singleflight.Group
}

// Conn is a connection to a rados with last access time stored in it.
type Conn struct {
	client           radosConnection
	lastAccessTime   time.Time
	lastAccessTimeMu sync.RWMutex
}

// NewManager initializes connManager structure and runs Go Routine that watches for unused connections.
func NewManager(
	keepAlive time.Duration,
	timeout int,
	logger log.Logger,
) *ConnManager {
	ctx, cancel := context.WithCancel(context.Background())

	connMgr := &ConnManager{
		connections:    &zbxsync.SyncMap[uri.URI, *Conn]{},
		keepAlive:      keepAlive,
		connectTimeout: timeout,
		callTimeout:    timeout,
		destroy:        cancel, // Destroy stops originated goroutines and closes connections.
		log:            logger,
	}

	go connMgr.housekeeper(ctx, housekeeperInterval)

	return connMgr
}

// GetConnection returns an existing connection or creates a new one.
func (m *ConnManager) GetConnection(u *uri.URI) (*Conn, error) {
	conn, err := m.getConn(u)
	if err != nil {
		return nil, errs.Wrap(err, "cannot get connection")
	}

	m.log.Tracef("connection found for key: " + u.Addr())

	conn.updateLastAccessTime()

	return conn, nil
}

// Close gracefully closes all connections and started goroutines.
func (m *ConnManager) Close() {
	m.destroy()
}

// Command executes a command against a Ceph monitor. It sends the provided
// arguments and returns the resulting output data, a status string, and any
// error that occurred during execution.
func (c *Conn) Command(args []byte) ([]byte, string, error) {
	return c.client.MonCommand(args) //nolint:wrapcheck // just isolated client from the struct.
}

// getConn retrieves a connection, creating it if it doesn't exist.
// This is the most robust and performant implementation.
func (m *ConnManager) getConn(u *uri.URI) (*Conn, error) {
	cachedConn, ok := m.connections.Load(*u)

	if ok {
		return cachedConn, nil
	}

	// We use singleflight to ensure only one connection attempt occurs at a time.
	// If the connection is not yet ready, any subsequent requests will wait for the first
	// attempt to complete. This prevents the creation of multiple redundant connections
	// that would then need to be closed.
	// But this singleflight implementation does not guarantee that we will get
	// the same connection. So we need to make second check after we got response,
	// so singleflight is great optimization tool for many concurrent creation tasks.
	newConnInterface, err, _ := m.createGroup.Do(u.String(), func() (any, error) {
		createdConn, err := m.createConn(u)
		if err != nil {
			return nil, err
		}

		return createdConn, nil
	})
	if err != nil {
		return nil, errs.Wrap(err, "cannot create connection "+u.Addr())
	}

	connection, ok := newConnInterface.(*Conn)
	if !ok {
		return nil, errs.New("cannot cast to rados connection " + u.Addr())
	}

	cachedConn, ok = m.connections.Load(*u)
	if !ok {
		m.connections.Store(*u, connection)

		return connection, nil
	}

	go connection.client.Shutdown() // sending to the back thread to speed up returnal of the newly created conn.

	return cachedConn, nil
}

// createConn is now a private helper that just does the work without any locking.
func (m *ConnManager) createConn(u *uri.URI) (*Conn, error) {
	radosConn, err := newRadosConn(u.User())
	if err != nil {
		return nil, errs.Wrap(err, "failed to create connection")
	}

	err = radosConn.SetConfigOption("mon_host", u.Addr())
	if err != nil {
		return nil, errs.Wrap(err, "failed to set config option monitor host")
	}

	err = radosConn.SetConfigOption("key", u.Password())
	if err != nil {
		return nil, errs.Wrap(err, "failed to set config option key")
	}

	err = radosConn.SetConfigOption("rados_mon_op_timeout", strconv.Itoa(m.callTimeout))
	if err != nil {
		return nil, errs.Wrap(err, "failed to set config option monitor timeout")
	}

	err = radosConn.SetConfigOption("client_mount_timeout", strconv.Itoa(m.connectTimeout))
	if err != nil {
		return nil, errs.Wrap(err, "failed to set config option client mount timeout")
	}

	err = radosConn.Connect()
	if err != nil {
		return nil, errs.Wrap(err, "failed to connect")
	}

	connection := &Conn{
		client:         radosConn,
		lastAccessTime: time.Now(),
	}

	return connection, nil
}

// updateLastAccessTime updates the last time a connection was accessed.
func (c *Conn) updateLastAccessTime() {
	c.lastAccessTimeMu.Lock()
	defer c.lastAccessTimeMu.Unlock()

	c.lastAccessTime = time.Now()
}

func (c *Conn) getLastAccessTime() time.Time {
	c.lastAccessTimeMu.RLock()
	defer c.lastAccessTimeMu.RUnlock()

	return c.lastAccessTime
}

// closeUnused identifies and closes connections that have been idle for longer than the keep-alive interval.
func (m *ConnManager) closeUnused() {
	toClose := make(map[uri.URI]*Conn, 2) // usually no more than 2 connections will be closed on timeout.

	m.connections.Range(func(connectionKey uri.URI, connection *Conn) bool {
		if time.Since(connection.getLastAccessTime()) > m.keepAlive {
			toClose[connectionKey] = connection

			m.connections.Delete(connectionKey)
		}

		return true
	})

	for u, connection := range toClose {
		connection.client.Shutdown()
		m.log.Debugf("Closed unused connection: %s", u.Addr())
	}
}

// closeAll closes all existing connections.
func (m *ConnManager) closeAll() {
	oldConns := m.connections
	m.connections = &zbxsync.SyncMap[uri.URI, *Conn]{}

	oldConns.Range(func(_ uri.URI, connection *Conn) bool {
		connection.client.Shutdown()

		return true
	})

}

// housekeeper repeatedly checks for unused connections and closes them.
func (m *ConnManager) housekeeper(ctx context.Context, interval time.Duration) {
	ticker := time.NewTicker(interval)

	for {
		select {
		case <-ctx.Done():
			ticker.Stop()
			m.closeAll()

			return
		case <-ticker.C:
			m.closeUnused()
		}
	}
}
