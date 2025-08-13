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
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin/comms"
	"golang.zabbix.com/sdk/tlsconfig"
	"golang.zabbix.com/sdk/uri"
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

// Manager is thread-safe structure for manage connections.
type Manager struct {
	log          log.Logger
	managerMutex sync.Mutex
	connMutex    sync.Mutex
	connections  map[connKey]*RedisConn
	keepAlive    time.Duration
	timeout      time.Duration
	Destroy      context.CancelFunc
}

type connKey struct {
	uri        uri.URI
	rawURI     string
	tlsConnect string
	tlsCA      string
	tlsCert    string
	tlsKey     string
}

// NewRedisConn creates a new RedisConnection that keeps time of the last access.
func NewRedisConn(client radix.Client) *RedisConn {
	return &RedisConn{
		client:         client,
		lastTimeAccess: time.Now(),
	}
}

// NewManager initializes Manager structure and runs Go Routine that watches for unused connections.
func NewManager(logger log.Logger, keepAlive, timeout, hkInterval time.Duration) *Manager {
	ctx, cancel := context.WithCancel(context.Background())

	connMgr := &Manager{
		log:         logger,
		connections: make(map[connKey]*RedisConn),
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
	if err != nil {
		return errs.Wrap(err, "query failed")
	}

	return nil
}

// GetConnection returns an existing connection or creates a new one.
func (m *Manager) GetConnection(u *uri.URI, params map[string]string) (*RedisConn, error) {
	ck := createConnKey(u, params)

	m.managerMutex.Lock()
	defer m.managerMutex.Unlock()

	conn := m.get(ck)

	if conn == nil {
		var err error

		conn, err = m.create(ck)
		if err != nil {
			return nil, errs.WrapConst(err, zbxerr.ErrorConnectionFailed)
		}
	}

	return conn, nil
}

func createConnKey(u *uri.URI, params map[string]string) *connKey {
	tlsType := params[string(comms.TLSConnect)]
	if tlsType == "" {
		tlsType = string(tlsconfig.Disabled)
	}

	return &connKey{
		uri:        *u,
		rawURI:     params[string(comms.URI)],
		tlsConnect: tlsType,
		tlsCA:      params[string(comms.TLSCAFile)],
		tlsCert:    params[string(comms.TLSCertFile)],
		tlsKey:     params[string(comms.TLSKeyFile)],
	}
}

// updateAccessTime updates the last time a connection was accessed.
func (r *RedisConn) updateAccessTime() {
	r.lastTimeAccess = time.Now()
}

// closeUnused closes each connection that has not been accessed at least within the keepalive interval.
func (m *Manager) closeUnused() {
	m.connMutex.Lock()
	defer m.connMutex.Unlock()

	for u, conn := range m.connections {
		if time.Since(conn.lastTimeAccess) > m.keepAlive {
			err := conn.client.Close()
			if err == nil {
				delete(m.connections, u)
				m.log.Debugf("Closed unused connection: %s", u.uri.Addr())
			} else {
				m.log.Errf("Error occurred while closing connection: %s", u.uri.Addr())
			}
		}
	}
}

// closeAll closes all existed connections.
func (m *Manager) closeAll() {
	m.connMutex.Lock()

	for u, conn := range m.connections {
		err := conn.client.Close()
		if err == nil {
			delete(m.connections, u)
		} else {
			m.log.Errf("Error occurred while closing connection: %s", u.uri.Addr())
		}
	}

	m.connMutex.Unlock()
}

// housekeeper repeatedly checks for unused connections and close them.
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

// create creates a new connection with given credentials.
func (m *Manager) create(u *connKey) (*RedisConn, error) {
	const clientName = "zbx_monitor"

	const poolSize = 1

	m.connMutex.Lock()
	defer m.connMutex.Unlock()

	tlsConfig, err := getTLSConfig(u)
	if err != nil {
		return nil, errs.Wrap(err, "failed to get tls config")
	}

	_, ok := m.connections[*u]
	if ok {
		// Should never happen.
		panic("connection already exists")
	}

	// authConnFunc is used as radix.ConnFunc to perform AUTH and set timeout.
	authConnFunc := func(scheme, addr string) (radix.Conn, error) {
		dialOpts := []radix.DialOpt{
			radix.DialTimeout(m.timeout),
			radix.DialAuthUser(u.uri.User(), u.uri.Password()),
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

	client, err := radix.NewPool(u.uri.Scheme(), u.uri.Addr(), poolSize, radix.PoolConnFunc(authConnFunc))
	if err != nil {
		return nil, errs.Wrap(err, "failed to create pool")
	}

	m.connections[*u] = NewRedisConn(client)

	m.log.Debugf("Created new connection: %s", u.uri.Addr())

	return m.connections[*u], nil
}

// get returns a connection with given cid if it exists and also updates lastTimeAccess, otherwise returns nil.
func (m *Manager) get(u *connKey) *RedisConn {
	m.connMutex.Lock()
	defer m.connMutex.Unlock()

	conn, ok := m.connections[*u]
	if ok {
		conn.updateAccessTime()

		return conn
	}

	return nil
}
