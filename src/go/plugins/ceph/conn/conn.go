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
)

const housekeeperInterval = 10 * time.Second

// Manager is thread-safe structure for manage connections.
type Manager struct {
	connectionsMu  sync.RWMutex
	connections    map[uri.URI]*Conn
	keepAlive      time.Duration
	connectTimeout int // seconds
	callTimeout    int // seconds
	log            log.Logger
	destroy        context.CancelFunc
	createGroup    singleflight.Group // Used to ensure only one creation happens per key
}

// Conn is a connection to a rados with last access time stored in it.
type Conn struct {
	client           *rados.Conn
	lastAccessTime   time.Time
	lastAccessTimeMu sync.RWMutex
}

// NewManager initializes connManager structure and runs Go Routine that watches for unused connections.
func NewManager(
	keepAlive time.Duration,
	timeout int,
	logger log.Logger,
) *Manager {
	ctx, cancel := context.WithCancel(context.Background())

	connMgr := &Manager{
		connections:    make(map[uri.URI]*Conn),
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
func (m *Manager) GetConnection(u *uri.URI) (*Conn, error) {
	conn, err := m.getConn(u)
	if err != nil {
		return nil, errs.Wrap(err, "cannot get connection")
	}

	m.log.Tracef("connection found for key: " + u.Addr())

	conn.updateLastAccessTime()

	return conn, nil
}

// Close gracefully closes all connections and started goroutines.
func (m *Manager) Close() {
	m.closeAll()
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
func (m *Manager) getConn(u *uri.URI) (*Conn, error) {
	m.connectionsMu.RLock()
	conn, ok := m.connections[*u]
	m.connectionsMu.RUnlock()

	if ok {
		return conn, nil
	}

	// The `Do` method takes a key and a function. It guarantees that for a
	// given key, the function will only be executed by one goroutine.
	// Other goroutines calling `Do` with the same key will wait.
	newConnInterface, err, _ := m.createGroup.Do(u.String(), func() (any, error) {
		// This block is the "work" function. It's protected by the singleflight group.
		// The expensive connection logic is here,
		// safely outside any global lock to maintain ability getting connections.
		createdConn, err := m.createConn(u)
		if err != nil {
			return nil, err
		}

		// Now that we have a connection, acquire the write lock for the
		// brief moment it takes to store it in the map.
		m.connectionsMu.Lock()
		m.connections[*u] = createdConn
		m.connectionsMu.Unlock()

		return createdConn, nil
	})

	if err != nil {
		return nil, errs.Wrap(err, "cannot create connection "+u.Addr())
	}

	// in golang casting function is very efficient
	connection, ok := newConnInterface.(*Conn)
	if !ok {
		return nil, errs.New("cannot cast to rados connection " + u.Addr())
	}

	return connection, nil
}

// createConn is now a private helper that just does the work without any locking.
func (m *Manager) createConn(u *uri.URI) (*Conn, error) {
	radosConn, err := rados.NewConnWithUser(u.User())
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
func (m *Manager) closeUnused() {
	type connectionToClose struct {
		uri  uri.URI
		conn *Conn
	}

	var toClose []*connectionToClose

	m.connectionsMu.Lock()
	// 1. Identify connections to close and collect their details.
	for connectionKey, connection := range m.connections {
		if time.Since(connection.getLastAccessTime()) > m.keepAlive {
			toClose = append(toClose, &connectionToClose{uri: connectionKey, conn: connection})
			delete(m.connections, connectionKey) // This is safe in Go
		}
	}

	m.connectionsMu.Unlock() // 2. Release the lock as soon as the map is updated.

	// 3. Perform the slow I/O operations without holding the lock.
	for _, item := range toClose {
		item.conn.client.Shutdown()
		m.log.Debugf("Closed unused connection: %s", item.uri.Addr())
	}
}

// closeAll closes all existing connections.
func (m *Manager) closeAll() {
	m.connectionsMu.Lock()
	// 1. Atomically grab the old map and replace it with a new one.
	oldConns := m.connections
	m.connections = make(map[uri.URI]*Conn)
	m.connectionsMu.Unlock() // 2. Release the lock immediately.

	for _, conn := range oldConns {
		conn.client.Shutdown()
	}
}

// housekeeper repeatedly checks for unused connections and closes them.
func (m *Manager) housekeeper(ctx context.Context, interval time.Duration) {
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
