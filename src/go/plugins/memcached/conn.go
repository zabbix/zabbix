/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

package memcached

import (
	"context"
	"github.com/alimy/mc/v2"
	"sync"
	"time"
	"zabbix.com/pkg/log"
)

type mcClient interface {
	Stats(key string) (mc.McStats, error)
	NoOp() error
}

type mcConn struct {
	client         mc.Client
	lastTimeAccess time.Time
}

// Stats wraps the mc.Client.StatsWithKey function.
func (c *mcConn) Stats(key string) (stats mc.McStats, err error) {
	res, err := c.client.StatsWithKey(key)
	if err != nil {
		return nil, err
	}
	if len(res) == 0 {
		return nil, errorEmptyResult
	}
	if len(res) > 1 {
		panic("unexpected result")
	}

	// get the only entry of stats
	for _, stats = range res {
		break
	}

	return
}

// NoOp wraps the mc.Client.NoOp function.
func (c *mcConn) NoOp() error {
	return c.client.NoOp()
}

// updateAccessTime updates the last time a connection was accessed.
func (r *mcConn) updateAccessTime() {
	r.lastTimeAccess = time.Now()
}

// Thread-safe structure for manage connections.
type connManager struct {
	sync.Mutex
	connMutex   sync.Mutex
	connections map[URI]*mcConn
	keepAlive   time.Duration
	timeout     time.Duration
	Destroy     context.CancelFunc
}

// NewConnManager initializes connManager structure and runs Go Routine that watches for unused connections.
func NewConnManager(keepAlive, timeout time.Duration) *connManager {

	ctx, cancel := context.WithCancel(context.Background())

	connMgr := &connManager{
		connections: make(map[URI]*mcConn),
		keepAlive:   keepAlive,
		timeout:     timeout,
		Destroy:     cancel, // Destroy stops originated goroutines and close connections.
	}

	go connMgr.housekeeper(ctx)

	return connMgr
}

// closeUnused closes each connection that has not been accessed at least within the keepalive interval.
func (c *connManager) closeUnused() {
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
func (c *connManager) closeAll() {
	c.connMutex.Lock()
	for uri, conn := range c.connections {
		conn.client.Quit()
		delete(c.connections, uri)
	}
	c.connMutex.Unlock()
}

// housekeeper repeatedly checks for unused connections and close them.
func (c *connManager) housekeeper(ctx context.Context) {
	for range time.Tick(10 * time.Second) {
		select {
		case <-ctx.Done():
			c.closeAll()
			return
		default:
			c.closeUnused()
		}
	}
}

// create creates a new connection with a given URI and password.
func (c *connManager) create(uri URI) *mcConn {
	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	if _, ok := c.connections[uri]; ok {
		// Should never happen.
		panic("connection already exists")
	}

	client := mc.NewMCwithConfig(
		uri.Addr(),
		uri.User(),
		uri.Password(),
		&mc.Config{
			Hasher:             mc.NewModuloHasher(),
			Retries:            2,
			RetryDelay:         200 * time.Millisecond,
			Failover:           true,
			ConnectionTimeout:  c.timeout,
			DownRetryDelay:     60 * time.Second,
			PoolSize:           1,
			TcpKeepAlive:       true,
			TcpKeepAlivePeriod: c.keepAlive,
			TcpNoDelay:         true,
		},
	)

	c.connections[uri] = &mcConn{
		client:         *client,
		lastTimeAccess: time.Now(),
	}

	log.Debugf("[%s] Created new connection: %s", pluginName, uri.Addr())

	return c.connections[uri]
}

// get returns a connection with given uri if it exists and also updates lastTimeAccess, otherwise returns nil.
func (c *connManager) get(uri URI) *mcConn {
	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	if conn, ok := c.connections[uri]; ok {
		conn.updateAccessTime()
		return conn
	}

	return nil
}

// GetConnection returns an existing connection or creates a new one.
func (c *connManager) GetConnection(uri URI) (conn *mcConn) {
	c.Lock()
	defer c.Unlock()

	conn = c.get(uri)

	if conn == nil {
		conn = c.create(uri)
	}

	return
}
