package mongodb

import (
	"errors"
	"fmt"
	"time"

	"gopkg.in/mgo.v2"
	"gopkg.in/mgo.v2/bson"
	"zabbix.com/pkg/zbxerr"
)

const (
	mustFail = "mustFail"
)

type MockConn struct {
	dbs map[string]*MockMongoDatabase
}

func NewMockConn() *MockConn {
	return &MockConn{
		dbs: make(map[string]*MockMongoDatabase),
	}
}

func (conn *MockConn) DB(name string) Database {
	if db, ok := conn.dbs[name]; ok {
		return db
	}

	conn.dbs[name] = &MockMongoDatabase{
		name:        name,
		collections: make(map[string]*MockMongoCollection),
	}

	return conn.dbs[name]
}

func (conn *MockConn) DatabaseNames() (names []string, err error) {
	for _, db := range conn.dbs {
		if db.name == mustFail {
			return nil, zbxerr.ErrorCannotFetchData
		}

		names = append(names, db.name)
	}

	return
}

func (conn *MockConn) Ping() error {
	return nil
}

func (conn *MockConn) GetMaxTimeMS() int64 {
	return 3000
}

type MockSession interface {
	DB(name string) Database
	DatabaseNames() (names []string, err error)
	GetMaxTimeMS() int64
	Ping() error
}

type MockMongoDatabase struct {
	name        string
	collections map[string]*MockMongoCollection
	RunFunc     func(dbName, cmd string) ([]byte, error)
}

func (d *MockMongoDatabase) C(name string) Collection {
	if col, ok := d.collections[name]; ok {
		return col
	}

	d.collections[name] = &MockMongoCollection{
		name:    name,
		queries: make(map[interface{}]*MockMongoQuery),
	}

	return d.collections[name]
}

func (d *MockMongoDatabase) CollectionNames() (names []string, err error) {
	for _, col := range d.collections {
		if col.name == mustFail {
			return nil, errors.New("fail")
		}

		names = append(names, col.name)
	}

	return
}

func (d *MockMongoDatabase) Run(cmd, result interface{}) error {
	if d.RunFunc == nil {
		d.RunFunc = func(dbName, _ string) ([]byte, error) {
			if dbName == mustFail {
				return nil, errors.New("fail")
			}

			return bson.Marshal(map[string]int{"ok": 1})
		}
	}

	if result == nil {
		return nil
	}

	bsonDcmd := *(cmd.(*bson.D))
	cmdName := bsonDcmd[0].Name

	data, err := d.RunFunc(d.name, cmdName)
	if err != nil {
		return err
	}

	return bson.Unmarshal(data, result)
}

type MockMongoCollection struct {
	name    string
	queries map[interface{}]*MockMongoQuery
}

func (c *MockMongoCollection) Find(query interface{}) Query {
	queryHash := fmt.Sprintf("%v", query)
	if q, ok := c.queries[queryHash]; ok {
		return q
	}

	c.queries[queryHash] = &MockMongoQuery{
		collection: c.name,
		query:      query,
	}

	return c.queries[queryHash]
}

type MockMongoQuery struct {
	collection string
	query      interface{}
	sortFields []string
	DataFunc   func(collection string, query interface{}, sortFields ...string) ([]byte, error)
}

func (q *MockMongoQuery) retrieve(result interface{}) error {
	if q.DataFunc == nil {
		return mgo.ErrNotFound
	}

	if result == nil {
		return nil
	}

	data, err := q.DataFunc(q.collection, q.query, q.sortFields...)
	if err != nil {
		return err
	}

	return bson.Unmarshal(data, result)
}

func (q *MockMongoQuery) All(result interface{}) error {
	return q.retrieve(result)
}

func (q *MockMongoQuery) Count() (n int, err error) {
	return 1, nil
}

func (q *MockMongoQuery) Limit(n int) Query {
	return q
}

func (q *MockMongoQuery) One(result interface{}) error {
	return q.retrieve(result)
}

func (q *MockMongoQuery) SetMaxTime(_ time.Duration) Query {
	return q
}

func (q *MockMongoQuery) Sort(fields ...string) Query {
	q.sortFields = fields
	return q
}
