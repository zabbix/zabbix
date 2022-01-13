/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

package mongodb

import (
	"context"
	"sync"
	"time"

	"gopkg.in/mgo.v2/bson"

	"gopkg.in/mgo.v2"
	"zabbix.com/pkg/log"
	"zabbix.com/pkg/uri"
)

type MongoConn struct {
	addr           string
	timeout        time.Duration
	lastTimeAccess time.Time
	session        *mgo.Session
}

// DB shadows *mgo.DB to returns a Database interface instead of *mgo.Database.
func (conn *MongoConn) DB(name string) Database {
	conn.checkConnection()

	return &MongoDatabase{Database: conn.session.DB(name)}
}

func (conn *MongoConn) DatabaseNames() (names []string, err error) {
	conn.checkConnection()

	return conn.session.DatabaseNames()
}

func (conn *MongoConn) Ping() error {
	return conn.session.DB("admin").Run(&bson.D{
		bson.DocElem{
			Name:  "ping",
			Value: 1,
		},
		bson.DocElem{
			Name:  "maxTimeMS",
			Value: conn.GetMaxTimeMS(),
		},
	}, nil)
}

func (conn *MongoConn) GetMaxTimeMS() int64 {
	return conn.timeout.Milliseconds()
}

// updateAccessTime updates the last time a connection was accessed.
func (conn *MongoConn) updateAccessTime() {
	conn.lastTimeAccess = time.Now()
}

// checkConnection implements db reconnection.
func (conn *MongoConn) checkConnection() {
	if err := conn.Ping(); err != nil {
		conn.session.Refresh()
		log.Debugf("[%s] Attempt to reconnect: %s", pluginName, conn.addr)
	}
}

// Session is an interface to access to the session struct.
type Session interface {
	DB(name string) Database
	DatabaseNames() (names []string, err error)
	GetMaxTimeMS() int64
	Ping() error
}

// Database is an interface to access to the database struct.
type Database interface {
	C(name string) Collection
	CollectionNames() (names []string, err error)
	Run(cmd, result interface{}) error
}

// MongoDatabase wraps a mgo.Database to embed methods in models.
type MongoDatabase struct {
	*mgo.Database
}

// C shadows *mgo.DB to returns a Database interface instead of *mgo.Database.
func (d *MongoDatabase) C(name string) Collection {
	return &MongoCollection{Collection: d.Database.C(name)}
}

func (d *MongoDatabase) CollectionNames() (names []string, err error) {
	return d.Database.CollectionNames()
}

// Run shadows *mgo.DB to returns a Database interface instead of *mgo.Database.
func (d *MongoDatabase) Run(cmd, result interface{}) error {
	return d.Database.Run(cmd, result)
}

// MongoCollection wraps a mgo.Collection to embed methods in models.
type MongoCollection struct {
	*mgo.Collection
}

// Collection is an interface to access to the collection struct.
type Collection interface {
	Find(query interface{}) Query
}

// Find shadows *mgo.Collection to returns a Query interface instead of *mgo.Query.
func (c *MongoCollection) Find(query interface{}) Query {
	return &MongoQuery{Query: c.Collection.Find(query)}
}

// Query is an interface to access to the query struct
type Query interface {
	All(result interface{}) error
	Count() (n int, err error)
	Limit(n int) Query
	One(result interface{}) error
	SetMaxTime(d time.Duration) Query
	Sort(fields ...string) Query
}

// MongoQuery wraps a mgo.Query to embed methods in models.
type MongoQuery struct {
	*mgo.Query
}

func (q *MongoQuery) Limit(n int) Query {
	q.Query.Limit(n)
	return q
}

func (q *MongoQuery) SetMaxTime(d time.Duration) Query {
	q.Query.SetMaxTime(d)
	return q
}

func (q *MongoQuery) Sort(fields ...string) Query {
	q.Query.Sort(fields...)
	return q
}

// ConnManager is thread-safe structure for manage connections.
type ConnManager struct {
	sync.Mutex
	connMutex   sync.Mutex
	connections map[uri.URI]*MongoConn
	keepAlive   time.Duration
	timeout     time.Duration
	Destroy     context.CancelFunc
}

// NewConnManager initializes connManager structure and runs Go Routine that watches for unused connections.
func NewConnManager(keepAlive, timeout, hkInterval time.Duration) *ConnManager {
	ctx, cancel := context.WithCancel(context.Background())

	connMgr := &ConnManager{
		connections: make(map[uri.URI]*MongoConn),
		keepAlive:   keepAlive,
		timeout:     timeout,
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
			conn.session.Close()
			delete(c.connections, uri)
			log.Debugf("[%s] Closed unused connection: %s", pluginName, uri.Addr())
		}
	}
}

// closeAll closes all existed connections.
func (c *ConnManager) closeAll() {
	c.connMutex.Lock()
	for uri, conn := range c.connections {
		conn.session.Close()
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
func (c *ConnManager) create(uri uri.URI) (*MongoConn, error) {
	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	if _, ok := c.connections[uri]; ok {
		// Should never happen.
		panic("connection already exists")
	}

	session, err := mgo.DialWithInfo(&mgo.DialInfo{
		Addrs:     []string{uri.Addr()},
		Direct:    true,
		FailFast:  false,
		Password:  uri.Password(),
		PoolLimit: 1,
		Timeout:   c.timeout,
		Username:  uri.User(),
	})
	if err != nil {
		return nil, err
	}

	// Read from one of the nearest members, irrespective of it being primary or secondary.
	session.SetMode(mgo.Nearest, true)

	c.connections[uri] = &MongoConn{
		addr:           uri.Addr(),
		timeout:        c.timeout,
		lastTimeAccess: time.Now(),
		session:        session,
	}

	log.Debugf("[%s] Created new connection: %s", pluginName, uri.Addr())

	return c.connections[uri], nil
}

// get returns a connection with given uri if it exists and also updates lastTimeAccess, otherwise returns nil.
func (c *ConnManager) get(uri uri.URI) *MongoConn {
	c.connMutex.Lock()
	defer c.connMutex.Unlock()

	if conn, ok := c.connections[uri]; ok {
		conn.updateAccessTime()
		return conn
	}

	return nil
}

// GetConnection returns an existing connection or creates a new one.
func (c *ConnManager) GetConnection(uri uri.URI) (conn *MongoConn, err error) {
	c.Lock()
	defer c.Unlock()

	conn = c.get(uri)

	if conn == nil {
		conn, err = c.create(uri)
	}

	return
}
