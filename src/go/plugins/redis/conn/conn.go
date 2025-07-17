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
	"errors"
	"sync"
	"time"

	"github.com/mediocregopher/radix/v3"
	"golang.zabbix.com/agent2/plugins/redis/info"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	sdkuri "golang.zabbix.com/sdk/uri"
	"golang.zabbix.com/sdk/zbxerr"
)

// HouseKeeperInterval is seconds of house keepers interval.
const HouseKeeperInterval = 10

//nolint:revive,staticcheck //this is a direct error from old redis
var errMasterDown = errors.New("MASTERDOWN Link with MASTER is down and slave-serve-stale-data is set to 'no'.")

// RedisClient defines an interface for a client that can execute Redis commands.
type RedisClient interface {
	Query(cmd radix.CmdAction) error
}

// RedisConn encapsulates a connection to a Redis client and tracks its last access time.
type RedisConn struct {
	client         radix.Client
	lastTimeAccess time.Time
}

// ConnManager is thread-safe structure for manage connections.
type ConnManager struct {
	managerMutex sync.Mutex
	connMutex    sync.Mutex
	connections  map[*sdkuri.URI]*RedisConn
	keepAlive    time.Duration
	timeout      time.Duration
	Destroy      context.CancelFunc
}

// NewRedisConn creates a new RedisConnection that keeps time of the last access.
func NewRedisConn(client radix.Client) *RedisConn {
	return &RedisConn{
		client:         client,
		lastTimeAccess: time.Now(),
	}
}

// NewConnManager initializes ConnManager structure and runs Go Routine that watches for unused connections.
func NewConnManager(keepAlive, timeout, hkInterval time.Duration) *ConnManager {
	ctx, cancel := context.WithCancel(context.Background())

	connMgr := &ConnManager{
		connections: make(map[*sdkuri.URI]*RedisConn),
		keepAlive:   keepAlive,
		timeout:     timeout,
		Destroy:     cancel, // Destroy stops originated goroutines and close connections.
	}

	go connMgr.housekeeper(ctx, hkInterval)

	return connMgr
}

// Query wraps the radix.Client.Do function.
func (r *RedisConn) Query(cmd radix.CmdAction) error {
	err := r.client.Do(cmd)

	return errs.Wrap(err, "query failed")
}

// GetConnection returns an existing connection or creates a new one.
func (c *ConnManager) GetConnection(uri *sdkuri.URI, params map[string]string) (*RedisConn, error) {
	c.managerMutex.Lock()
	defer c.managerMutex.Unlock()

	conn := c.get(uri)

	if conn == nil {
		var err error

		conn, err = c.create(uri, params)
		if err != nil {
			return nil, errs.WrapConst(err, zbxerr.ErrorConnectionFailed)
		}
	}

	return conn, nil
}

// updateAccessTime updates the last time a connection was accessed.
func (r *RedisConn) updateAccessTime() {
	r.lastTimeAccess = time.Now()
}

// closeUnused closes each connection that has not been accessed at least within the keepalive interval.
func (c *ConnManager) closeUnused() {
	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	for uri, conn := range c.connections {
		if time.Since(conn.lastTimeAccess) > c.keepAlive {
			err := conn.client.Close()
			if err == nil {
				delete(c.connections, uri)
				log.Debugf("[%s] Closed unused connection: %s", info.PluginName, uri.Addr())
			} else {
				log.Errf("[%s] Error occurred while closing connection: %s", info.PluginName, uri.Addr())
			}
		}
	}
}

// closeAll closes all existed connections.
func (c *ConnManager) closeAll() {
	c.connMutex.Lock()
	for u, conn := range c.connections {
		err := conn.client.Close()
		if err == nil {
			delete(c.connections, u)
		} else {
			log.Errf("[%s] Error occurred while closing connection: %s", info.PluginName, u.Addr())
		}
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
func (c *ConnManager) create(uri *sdkuri.URI, params map[string]string) (*RedisConn, error) {
	const clientName = "zbx_monitor"

	const poolSize = 1

	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	tlsConfig, err := getTLSConfig(uri, params)
	if err != nil {
		if !errors.Is(err, errTLSDisabled) {
			// If it's any other error, it's a real failure.
			return nil, errs.Wrap(err, "failed to get tls config")
		}
	}

	_, ok := c.connections[uri]
	if ok {
		// Should never happen.
		panic("connection already exists")
	}

	// authConnFunc is used as radix.ConnFunc to perform AUTH and set timeout.
	authConnFunc := func(scheme, addr string) (radix.Conn, error) {
		dialOpts := []radix.DialOpt{
			radix.DialTimeout(c.timeout),
			radix.DialAuthUser(uri.User(), uri.Password()),
		}

		if tlsConfig != nil {
			dialOpts = append(dialOpts, radix.DialUseTLS(tlsConfig))
		}

		conn, dialErr := radix.Dial(scheme, addr, dialOpts...)
		if dialErr != nil {
			return nil, errs.Wrap(dialErr, "failed to dial")
		}

		// Set name for connection. It will be showed in "client list" output.
		dialErr = conn.Do(radix.Cmd(nil, "CLIENT", "SETNAME", clientName))

		// The MASTERDOWN error can be returned by older Redis versions on commands
		// like CLIENT SETNAME even if the connection is usable.
		// We compare the error message string, as the error instance from the driver
		// will not be the same as our sentinel 'errMasterDown' variable.
		if dialErr != nil {
			if dialErr.Error() != errMasterDown.Error() || conn == nil {
				return nil, errs.Wrap(dialErr, "failed to set master down")
			}
		}

		return conn, nil
	}

	client, err := radix.NewPool(uri.Scheme(), uri.Addr(), poolSize, radix.PoolConnFunc(authConnFunc))
	if err != nil {
		return nil, errs.Wrap(err, "failed to create pool")
	}

	c.connections[uri] = &RedisConn{
		client:         client,
		lastTimeAccess: time.Now(),
	}

	log.Debugf("[%s] Created new connection: %s", info.PluginName, uri.Addr())

	return c.connections[uri], nil
}

// get returns a connection with given cid if it exists and also updates lastTimeAccess, otherwise returns nil.
func (c *ConnManager) get(uri *sdkuri.URI) *RedisConn {
	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	conn, ok := c.connections[uri]
	if ok {
		conn.updateAccessTime()

		return conn
	}

	return nil
}
