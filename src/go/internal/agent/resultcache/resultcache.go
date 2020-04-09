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

// package resultcache provides result caching component.
//
// ResultCache runs in separate goroutine, caches results and flushes data to the
// specified output interface in json format when requested or cache is full.
// The cache limits are specified by configuration file (BufferSize). If cache
// limits are reached the following logic is applied to new results:
// * non persistent results replaces either oldest result of the same item, or
//   oldest non persistent result if item was not yet cached.
// * persistent results replaces oldest non persistent result if the total number
//   of persistent results is less than half maximum cache size. Otherwise the result
//   is appended, extending cache beyond configured limit.
//
// Because of asynchronous nature of the communications it's not possible for
// result cache to return error if it cannot accept new persistent result. So
// instead before writing result to the cache the caller (plugin) must check
// the result cache state with PersistSlotsAvailable() function. This still
// can lead to more results written than cache limits allow. However it's not a
// big problem because cache buffer is not static and will be extended as required.
// The cache limit (BufferSize) is treated more like recommendation than hard limit.
//
package resultcache

import (
	"crypto/md5"
	"database/sql"
	"encoding/binary"
	"encoding/hex"
	"errors"
	"fmt"
	"time"

	"zabbix.com/internal/agent"
	"zabbix.com/pkg/plugin"
)

const (
	UploadRetryInterval = time.Second
)

type ResultCache interface {
	Start()
	Stop()
	UpdateOptions(options *agent.AgentOptions)
	Upload(u Uploader)
}

type baseCache struct {
	input    chan interface{}
	uploader Uploader
}

type AgentData struct {
	Id             uint64  `json:"id"`
	Itemid         uint64  `json:"itemid"`
	LastLogsize    *uint64 `json:"lastlogsize,omitempty"`
	Mtime          *int    `json:"mtime,omitempty"`
	State          *int    `json:"state,omitempty"`
	Value          *string `json:"value,omitempty"`
	EventSource    *string `json:"source,omitempty"`
	EventID        *int    `json:"eventid,omitempty"`
	EventSeverity  *int    `json:"severity,omitempty"`
	EventTimestamp *int    `json:"timestamp,omitempty"`
	Clock          int     `json:"clock,omitempty"`
	Ns             int     `json:"ns,omitempty"`
	persistent     bool
}

type AgentDataRequest struct {
	Request string       `json:"request"`
	Data    []*AgentData `json:"data"`
	Session string       `json:"session"`
	Host    string       `json:"host"`
	Version string       `json:"version"`
}

type Uploader interface {
	Write(data []byte, timeout time.Duration) (err error)
	Addr() (s string)
	CanRetry() (enabled bool)
}

func (c *baseCache) InitBase(u Uploader) {
	c.input = make(chan interface{}, 100)
	c.uploader = u
}

func (c *baseCache) Input() interface{} {
	return <-c.input
}

func (c *baseCache) Uploader() Uploader {
	return c.uploader
}

func (c *baseCache) Stop() {
	c.input <- nil
}

func (c *baseCache) Write(result *plugin.Result) {
	c.input <- result
}

func (c *baseCache) UpdateOptions(options *agent.AgentOptions) {
	c.input <- options
}

func (c *baseCache) Upload(u Uploader) {
	if u == nil {
		u = c.Uploader()
	}
	if u != nil {
		c.input <- u
	}
}

func (c *baseCache) Flush() {
	c.Upload(nil)
}

func newToken() string {
	h := md5.New()
	_ = binary.Write(h, binary.LittleEndian, time.Now().UnixNano())
	return hex.EncodeToString(h.Sum(nil))
}

func New(options *agent.AgentOptions, clientid uint64, output Uploader) ResultCache {
	if options.EnablePersistentBuffer == 0 {
		cache := &MemoryCache{clientID: clientid, token: newToken()}
		cache.init(output)
		return cache

	} else {
		cache := &DiskCache{clientID: clientid, token: newToken()}
		cache.init(output)
		return cache
	}
}

func createTableQuery(table string, id int) string {
	return fmt.Sprintf(
		"CREATE TABLE IF NOT EXISTS %s_%d ("+
			"id INTEGER,"+
			"write_clock INTEGER,"+
			"itemid INTEGER,"+
			"lastlogsize INTEGER,"+
			"mtime INTEGER,"+
			"state INTEGER,"+
			"value TEXT,"+
			"eventsource TEXT,"+
			"eventid INTEGER,"+
			"eventseverity INTEGER,"+
			"eventtimestamp INTEGER,"+
			"clock INTEGER,"+
			"ns INTEGER"+
			")",
		table, id)
}

func Prepare(options *agent.AgentOptions, addresses []string) (err error) {
	if options.EnablePersistentBuffer == 1 && options.PersistentBufferFile == "" {
		return errors.New("\"EnablePersistentBuffer\" parameter misconfiguration: \"PersistentBufferFile\" parameter is not set")
	}
	if options.EnablePersistentBuffer == 0 {
		if options.PersistentBufferFile != "" {
			return errors.New("\"PersistentBufferFile\" parameter is not empty but \"EnablePersistentBuffer\" is not set")
		}
		return
	}
	var database *sql.DB
	database, err = sql.Open("sqlite3", options.PersistentBufferFile)
	if err != nil {
		return fmt.Errorf("Cannot open database %s : %s.", options.PersistentBufferFile, err)
	}
	defer database.Close()
	stmt, _ := database.Prepare("CREATE TABLE IF NOT EXISTS registry (id INTEGER PRIMARY KEY,address TEXT,UNIQUE(address))")
	if _, err = stmt.Exec(); err != nil {
		return err
	}

	var id int
	var address string
	ids := make([]int, 0)
	registeredAddresses := make([]string, 0)
	rows, err := database.Query("SELECT id,address FROM registry")
	if err != nil {
		return err
	}
	for rows.Next() {
		if err = rows.Scan(&id, &address); err != nil {
			rows.Close()
			return err
		}
		ids = append(ids, id)
		registeredAddresses = append(registeredAddresses, address)
	}
	if err = rows.Err(); err != nil {
		return err
	}
addressCheck:
	for i, address := range registeredAddresses {
		for _, addr := range addresses {
			if addr == address {
				continue addressCheck
			}
		}
		if _, err = database.Exec(fmt.Sprintf("DELETE FROM registry WHERE ID = %d", ids[i])); err != nil {
			return err
		}
		if _, err = database.Exec(fmt.Sprintf("DROP TABLE data_%d", ids[i])); err != nil {
			return err
		}
		if _, err = database.Exec(fmt.Sprintf("DROP TABLE log_%d", ids[i])); err != nil {
			return err
		}
	}

	for _, addr := range addresses {
		stmt, err = database.Prepare("INSERT OR IGNORE INTO registry (address) VALUES (?)")
		if err != nil {
			return err
		}
		if _, err = stmt.Exec(addr); err != nil {
			return err
		}
		rows, err = database.Query("SELECT id FROM registry WHERE address=?", addr)
		if err != nil {
			return err
		}
		if !rows.Next() {
			if err = rows.Err(); err == nil {
				err = fmt.Errorf("Cannot select id for address %s", addr)
			}
			return err
		}
		if err = rows.Scan(&id); err != nil {
			return err
		}
		if err = rows.Close(); err != nil {
			return err
		}

		stmt, err = database.Prepare(createTableQuery("data", id))
		if err != nil {
			return err
		}
		if _, err = stmt.Exec(); err != nil {
			return err
		}
		if _, err = database.Exec(fmt.Sprintf("CREATE INDEX IF NOT EXISTS data_%d_1 ON data_%d (write_clock)", id, id)); err != nil {
			return err
		}

		fmt.Printf("ID: %d\n", id)
		stmt, err = database.Prepare(createTableQuery("log", id))
		if err != nil {
			return err
		}
		if _, err = stmt.Exec(); err != nil {
			return err
		}
		if _, err = database.Exec(fmt.Sprintf("CREATE INDEX IF NOT EXISTS log_%d_1 ON log_%d (write_clock)", id, id)); err != nil {
			return err
		}
		if _, err = database.Exec(fmt.Sprintf("DELETE FROM log_%d", id)); err != nil {
			return err
		}
	}
	return nil

}
