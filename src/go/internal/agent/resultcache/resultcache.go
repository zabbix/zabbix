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
	"os"
	"time"

	"git.zabbix.com/ap/plugin-support/log"
	"git.zabbix.com/ap/plugin-support/plugin"
	"zabbix.com/internal/agent"
)

const (
	UploadRetryInterval = time.Second
)

type ResultCache interface {
	Start()
	Stop()
	Upload(u Uploader)
	// TODO: will be used once the runtime configuration reload is implemented
	UpdateOptions(options *agent.AgentOptions)
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
	Write(data []byte, timeout time.Duration) (err []error)
	Addr() (s string)
	Hostname() (s string)
	CanRetry() (enabled bool)
}

// common cache data
type cacheData struct {
	log.Logger
	input      chan interface{}
	uploader   Uploader
	clientID   uint64
	token      string
	lastDataID uint64
	lastErrors []error
	retry      *time.Timer
	timeout    int
}

func (c *cacheData) Stop() {
	c.input <- nil
}

func (c *cacheData) Write(result *plugin.Result) {
	c.input <- result
}

// TODO: will be used once the runtime configuration reload is implemented
func (c *cacheData) UpdateOptions(options *agent.AgentOptions) {
	c.input <- options
}

func (c *cacheData) Upload(u Uploader) {
	if u == nil {
		u = c.uploader
	}
	if u != nil {
		c.input <- u
	}
}

func (c *cacheData) Flush() {
	c.Upload(nil)
}

func newToken() string {
	h := md5.New()
	_ = binary.Write(h, binary.LittleEndian, time.Now().UnixNano())
	return hex.EncodeToString(h.Sum(nil))
}

func tableName(prefix string, index int) string {
	return fmt.Sprintf("%s_%d", prefix, index)
}

// fetchRowAndClose fetches and scans the next row. False is returned if there are no
// rows to fetch or an error occurred.
func fetchRowAndClose(rows *sql.Rows, args ...interface{}) (ok bool, err error) {
	if rows.Next() {
		err = rows.Scan(args...)
		rows.Close()
		return err == nil, err
	}
	return false, rows.Err()
}

func New(options *agent.AgentOptions, clientid uint64, output Uploader) ResultCache {
	data := &cacheData{
		Logger:   log.New(fmt.Sprintf("%d", clientid)),
		clientID: clientid,
		input:    make(chan interface{}, 100),
		uploader: output,
		token:    newToken(),
	}

	if options.EnablePersistentBuffer == 0 {
		c := &MemoryCache{
			cacheData: data,
		}
		c.init(options)
		return c
	} else {
		c := &DiskCache{
			cacheData: data,
		}
		c.init(options)
		return c
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

func prepareDiskCache(options *agent.AgentOptions, addresses [][]string, hostnames []string) (err error) {
	type activeCombination struct {
		address  string
		hostname string
	}

	var database *sql.DB
	database, err = sql.Open("sqlite3", options.PersistentBufferFile)
	if err != nil {
		return fmt.Errorf("Cannot open database %s : %s.", options.PersistentBufferFile, err)
	}
	defer database.Close()

	stmt, err := database.Prepare("CREATE TABLE IF NOT EXISTS registry (id INTEGER PRIMARY KEY,address TEXT,hostname TEXT,UNIQUE(address,hostname))")
	if err != nil {
		return err
	}

	defer stmt.Close()

	if _, err = stmt.Exec(); err != nil {
		return err
	}

	var id int
	var address string
	var hostname string
	ids := make([]int, 0)
	combinations := make([]activeCombination, 0)
	registeredCombinations := make([]activeCombination, 0)

	for _, addr := range addresses {
		for _, host := range hostnames {
			combinations = append(combinations, activeCombination{address: addr[0], hostname: host})
		}
	}

	rows, err := database.Query("SELECT id,address,hostname FROM registry")
	if err != nil {
		return err
	}

	for rows.Next() {
		if err = rows.Scan(&id, &address, &hostname); err != nil {
			rows.Close()
			return err
		}
		ids = append(ids, id)
		registeredCombinations = append(registeredCombinations, activeCombination{address: address, hostname: hostname})
	}
	if err = rows.Err(); err != nil {
		return err
	}
addressCheck:
	for i, cr := range registeredCombinations {
		for _, c := range combinations {
			if c.address == cr.address && c.hostname == cr.hostname {
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

	for _, c := range combinations {
		stmt, err = database.Prepare("INSERT OR IGNORE INTO registry (address,hostname) VALUES (?,?)")
		if err != nil {
			return err
		}

		defer stmt.Close()

		if _, err = stmt.Exec(c.address, c.hostname); err != nil {
			return err
		}
		rows, err = database.Query("SELECT id FROM registry WHERE address=? AND hostname=?", c.address, c.hostname)
		if err != nil {
			return err
		}

		if ok, err := fetchRowAndClose(rows, &id); !ok {
			if err == nil {
				err = fmt.Errorf("cannot select id for address %s hostname %s", c.address, c.hostname)
			}
			return err
		}

		stmt, err = database.Prepare(createTableQuery("data", id))
		if err != nil {
			return err
		}

		defer stmt.Close()

		if _, err = stmt.Exec(); err != nil {
			return err
		}
		if _, err = database.Exec(fmt.Sprintf("CREATE INDEX IF NOT EXISTS data_%d_1 ON data_%d (write_clock)", id, id)); err != nil {
			return err
		}

		stmt, err = database.Prepare(createTableQuery("log", id))
		if err != nil {
			return err
		}

		defer stmt.Close()

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

func Prepare(options *agent.AgentOptions, addresses [][]string, hostnames []string) (err error) {
	if options.EnablePersistentBuffer == 1 && options.PersistentBufferFile == "" {
		return errors.New("\"EnablePersistentBuffer\" parameter misconfiguration: \"PersistentBufferFile\" parameter is not set")
	}
	if options.EnablePersistentBuffer == 0 {
		if options.PersistentBufferFile != "" {
			return errors.New("\"PersistentBufferFile\" parameter is not empty but \"EnablePersistentBuffer\" is not set")
		}
		return
	}

	if err = prepareDiskCache(options, addresses, hostnames); err != nil {
		if err = os.Remove(options.PersistentBufferFile); err != nil {
			return
		}
		err = prepareDiskCache(options, addresses, hostnames)
	}
	return
}
