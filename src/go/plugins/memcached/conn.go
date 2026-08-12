/*
** Copyright (C) 2001-2026 Zabbix SIA
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

package memcached

import (
	"context"
	"sync"
	"time"

	"github.com/memcachier/mc/v3"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/uri"
	"golang.zabbix.com/sdk/zbxerr"
)

const poolSize = 1

type MCClient interface {
	Stats(key string) (mc.McStats, error)
	NoOp() error
}

type MCConn struct {
	client         mc.Client
	lastTimeAccess time.Time
}

// stubConn for testing
type stubConn struct {
	NoOpFunc  func() error
	StatsFunc func(key string) (mc.McStats, error)
}

func (c *stubConn) Stats(key string) (mc.McStats, error) {
	return c.StatsFunc(key)
}

func (c *stubConn) NoOp() error {
	return c.NoOpFunc()
}

// Stats wraps the mc.Client.StatsWithKey function.
func (conn *MCConn) Stats(key string) (stats mc.McStats, err error) {
	res, err := conn.client.StatsWithKey(key)
	if err != nil {
		return nil, err
	}

	if len(res) == 0 {
		return nil, zbxerr.ErrorEmptyResult
	}

	if len(res) > 1 {
		panic("unexpected result")
	}

	// get the only entry of stats
	for _, stats = range res {
		break
	}

	return stats, err
}

// NoOp wraps the mc.Client.NoOp function.
func (conn *MCConn) NoOp() error {
	return conn.client.NoOp()
}

// updateAccessTime updates the last time a connection was accessed.
func (conn *MCConn) updateAccessTime() {
	conn.lastTimeAccess = time.Now()
}

// ConnManager is thread-safe structure for manage connections.
type ConnManager struct {
	sync.Mutex
	connMutex   sync.Mutex
	connections map[uri.URI]*MCConn
	keepAlive   time.Duration
	Destroy     context.CancelFunc
}

// NewConnManager initializes connManager structure and runs Go Routine that watches for unused connections.
func NewConnManager(keepAlive, hkInterval time.Duration) *ConnManager {
	ctx, cancel := context.WithCancel(context.Background())

	connMgr := &ConnManager{
		connections: make(map[uri.URI]*MCConn),
		keepAlive:   keepAlive,
		Destroy:     cancel, // Destroy stops originated goroutines and close connections.
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
			conn.client.Quit()
			delete(c.connections, uri)
			log.Debugf("[%s] Closed unused connection: %s", pluginName, uri.Addr())
		}
	}
}

// closeAll closes all existed connections.
func (c *ConnManager) closeAll() {
	c.connMutex.Lock()
	for uri, conn := range c.connections {
		conn.client.Quit()
		delete(c.connections, uri)
	}
	c.connMutex.Unlock()
}

// housekeeper repeatedly checks for unused connections and close them.
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
//
//nolint:gocritic,funcorder // we'll keep this in until the next refactor.
func (c *ConnManager) create(connectionURI uri.URI, connectionTimeout int) *MCConn {
	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	if _, ok := c.connections[connectionURI]; ok {
		// Should never happen.
		panic("connection already exists")
	}

	client := mc.NewMCwithConfig(
		connectionURI.String(),
		connectionURI.User(),
		connectionURI.Password(),
		&mc.Config{
			Hasher:             mc.NewModuloHasher(),
			Retries:            2,
			RetryDelay:         200 * time.Millisecond,
			Failover:           true,
			ConnectionTimeout:  time.Duration(connectionTimeout) * time.Second,
			DownRetryDelay:     60 * time.Second,
			PoolSize:           poolSize,
			TcpKeepAlive:       true,
			TcpKeepAlivePeriod: c.keepAlive,
			TcpNoDelay:         true,
		},
	)

	c.connections[connectionURI] = &MCConn{
		client:         *client,
		lastTimeAccess: time.Now(),
	}

	log.Debugf("[%s] Created new connection: %s", pluginName, connectionURI.Addr())

	return c.connections[connectionURI]
}

// get returns a connection with given uri if it exists and also updates lastTimeAccess, otherwise returns nil.
//
//nolint:gocritic,funcorder // we'll keep this in until the next refactor.
func (c *ConnManager) get(connectionURI uri.URI, _ int) *MCConn {
	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	if conn, ok := c.connections[connectionURI]; ok {
		conn.updateAccessTime()
		return conn
	}

	return nil
}

// GetConnection returns an existing connection or creates a new one.
//
//nolint:gocritic // we'll keep this in until the next refactor.
func (c *ConnManager) GetConnection(connectionURI uri.URI, connectionTimeout int) *MCConn {
	c.Lock()
	defer c.Unlock()

	conn := c.get(connectionURI, connectionTimeout)

	if conn == nil {
		conn = c.create(connectionURI, connectionTimeout)
	}

	return conn
}
