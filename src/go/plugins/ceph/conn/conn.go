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
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/uri"
)

const housekeeperInterval = 10 * time.Second

// Manager is thread-safe structure for manage connections.
type Manager struct {
	connectionsMu  sync.RWMutex
	connections    map[connKey]*Conn
	keepAlive      time.Duration
	connectTimeout int // seconds
	callTimeout    int // seconds
	log            log.Logger
	destroy        context.CancelFunc
}

// Conn is a connection to a rados with last access time stored in it.
type Conn struct {
	client           *rados.Conn
	lastAccessTime   time.Time
	lastAccessTimeMu sync.RWMutex
}

type connKey struct {
	uri      uri.URI
	apiKey   string
	username string
}

// NewManager initializes connManager structure and runs Go Routine that watches for unused connections.
func NewManager(
	keepAlive time.Duration,
	timeout int,
	logger log.Logger,
) *Manager {
	ctx, cancel := context.WithCancel(context.Background())

	connMgr := &Manager{
		connections:    make(map[connKey]*Conn),
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
func (c *Manager) GetConnection(u *uri.URI, params map[string]string) (*Conn, error) {
	ck := createConnKey(u, params)

	conn := c.getConn(ck)
	if conn != nil {
		c.log.Tracef("connection found for host: %s", u.Host())

		conn.updateLastAccessTime()

		return conn, nil
	}

	c.log.Tracef("creating new connection for host: %s", u.Host())

	conn, err := c.createConn(ck)
	if err != nil {
		return nil, err
	}

	return conn, nil
}

// Close gracefully closes all connections and started goroutines.
func (c *Manager) Close() {
	c.closeAll()
	c.destroy()
}

func createConnKey(u *uri.URI, params map[string]string) *connKey {
	return &connKey{
		uri:      *u,
		apiKey:   params["api_key"],  // todo make enums
		username: params["username"], // todo make enums
	}
}

// createConn creates a new connection with given credentials.
func (c *Manager) createConn(ck *connKey) (*Conn, error) {
	radosConn, err := rados.NewConnWithUser(ck.username)
	if err != nil {
		return nil, errs.Wrap(err, "failed to create connection")
	}

	err = radosConn.SetConfigOption("mon_host", ck.uri.String()) //todo test this
	if err != nil {
		return nil, errs.Wrap(err, "failed to set config option monitor host")
	}

	err = radosConn.SetConfigOption("key", ck.apiKey)
	if err != nil {
		return nil, errs.Wrap(err, "failed to set config option key")
	}

	err = radosConn.SetConfigOption("rados_mon_op_timeout", strconv.Itoa(c.callTimeout))
	if err != nil {
		return nil, errs.Wrap(err, "failed to set config option monitor timeout")
	}

	err = radosConn.SetConfigOption("client_mount_timeout", strconv.Itoa(c.connectTimeout))
	if err != nil {
		return nil, errs.Wrap(err, "failed to set config option client mount timeout")
	}

	err = radosConn.Connect()
	if err != nil {
		return nil, errs.Wrap(err, "failed to connect")
	}

	c.connectionsMu.Lock()
	defer c.connectionsMu.Unlock()

	// CRITICAL: Double-check. Another goroutine might have created the connection
	// in the small window while we were creating connection.
	cachedConn, ok := c.connections[*ck]
	if ok {
		log.Debugf("conn already exists: %s", ck.uri)

		go radosConn.Shutdown()

		return cachedConn, nil
	}

	connection := &Conn{
		client:           radosConn,
		lastAccessTimeMu: sync.RWMutex{},
		lastAccessTime:   time.Now(),
	}
	c.connections[*ck] = connection

	return connection, nil
}

func (c *Manager) getConn(ck *connKey) *Conn {
	c.connectionsMu.RLock()
	defer c.connectionsMu.RUnlock()

	conn, ok := c.connections[*ck]
	if !ok {
		return nil
	}

	return conn
}

// updateLastAccessTime updates the last time a connection was accessed.
func (conn *Conn) updateLastAccessTime() {
	conn.lastAccessTimeMu.Lock()
	defer conn.lastAccessTimeMu.Unlock()

	conn.lastAccessTime = time.Now()
}

func (conn *Conn) getLastAccessTime() time.Time {
	conn.lastAccessTimeMu.RLock()
	defer conn.lastAccessTimeMu.RUnlock()

	return conn.lastAccessTime
}

// closeUnused identifies and closes connections that have been idle for longer than the keep-alive interval.
func (c *Manager) closeUnused() {
	type connectionToClose struct {
		key  connKey
		conn *Conn
	}

	var toClose []*connectionToClose

	c.connectionsMu.Lock()
	// 1. Identify connections to close and collect their details.
	for connectionKey, connection := range c.connections {
		if time.Since(connection.getLastAccessTime()) > c.keepAlive {
			toClose = append(toClose, &connectionToClose{key: connectionKey, conn: connection})
			delete(c.connections, connectionKey) // This is safe in Go
		}
	}

	c.connectionsMu.Unlock() // 2. Release the lock as soon as the map is updated.

	// 3. Perform the slow I/O operations without holding the lock.
	for _, item := range toClose {
		item.conn.client.Shutdown()
		c.log.Debugf("Closed unused connection: %s", item.key.uri.Addr())
	}
}

// closeAll closes all existing connections.
func (c *Manager) closeAll() {
	c.connectionsMu.Lock()
	// 1. Atomically grab the old map and replace it with a new one.
	oldConns := c.connections
	c.connections = make(map[connKey]*Conn)
	c.connectionsMu.Unlock() // 2. Release the lock immediately.

	for _, conn := range oldConns {
		conn.client.Shutdown()
	}
}

// housekeeper repeatedly checks for unused connections and closes them.
func (c *Manager) housekeeper(ctx context.Context, interval time.Duration) {
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
