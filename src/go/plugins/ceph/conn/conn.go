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
	"fmt"
	"strconv"
	"sync"
	"time"

	"github.com/ceph/go-ceph/rados"
	"golang.org/x/sync/singleflight"
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
	createGroup    singleflight.Group // Used to ensure only one creation happens per key
}

// Conn is a connection to a rados with last access time stored in it.
type Conn struct {
	Client           *rados.Conn
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

	conn, err := c.getConn(ck)
	if err != nil {
		return nil, errs.Wrap(err, "cannot get connection")
	}

	c.log.Tracef("connection found for key: " + ck.string())

	conn.updateLastAccessTime()

	return conn, nil
}

func createConnKey(u *uri.URI, params map[string]string) *connKey {
	return &connKey{
		uri:      *u,
		apiKey:   params["APIKey"], // todo make enums
		username: params["User"],   // todo make enums
	}
}

// Close gracefully closes all connections and started goroutines.
func (c *Manager) Close() {
	c.closeAll()
	c.destroy()
}

func (ck *connKey) string() string {
	return fmt.Sprintf("%s@%s", ck.username, ck.uri.Addr())
}

// getConn retrieves a connection, creating it if it doesn't exist.
// This is the most robust and performant implementation.
func (c *Manager) getConn(ck *connKey) (*Conn, error) {
	c.connectionsMu.RLock()
	conn, ok := c.connections[*ck]
	c.connectionsMu.RUnlock()

	if ok {
		return conn, nil
	}

	// The `Do` method takes a key and a function. It guarantees that for a
	// given key, the function will only be executed by one goroutine.
	// Other goroutines calling `Do` with the same key will wait.
	newConnInterface, err, _ := c.createGroup.Do(ck.string(), func() (any, error) {
		// This block is the "work" function. It's protected by the singleflight group.
		// The expensive connection logic is here,
		// safely outside any global lock to maintain ability getting connections.
		createdConn, err := c.createConn(ck)
		if err != nil {
			return nil, err
		}

		// Now that we have a connection, acquire the write lock for the
		// brief moment it takes to store it in the map.
		c.connectionsMu.Lock()
		c.connections[*ck] = createdConn
		c.connectionsMu.Unlock()

		return createdConn, nil
	})

	if err != nil {
		return nil, errs.Wrap(err, "cannot create connection "+ck.string())
	}

	// in golang casting function is very efficient
	connection, ok := newConnInterface.(*Conn)
	if !ok {
		return nil, errs.New("cannot cast to rados connection " + ck.string())
	}

	return connection, nil
}

// createConn is now a private helper that just does the work without any locking.
func (c *Manager) createConn(ck *connKey) (*Conn, error) {
	radosConn, err := rados.NewConnWithUser(ck.username)
	if err != nil {
		return nil, errs.Wrap(err, "failed to create connection")
	}

	err = radosConn.SetConfigOption("mon_host", ck.uri.Addr())
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

	connection := &Conn{
		Client:         radosConn,
		lastAccessTime: time.Now(),
	}

	return connection, nil
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
		item.conn.Client.Shutdown()
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
		conn.Client.Shutdown()
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
