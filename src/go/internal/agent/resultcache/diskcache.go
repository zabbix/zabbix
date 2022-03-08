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
package resultcache

import (
	"database/sql"
	"encoding/json"
	"errors"
	"fmt"
	"reflect"
	"sync"
	"sync/atomic"
	"time"

	"zabbix.com/internal/agent"
	"zabbix.com/internal/monitor"
	"zabbix.com/pkg/itemutil"
	"zabbix.com/pkg/log"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/version"

	_ "github.com/mattn/go-sqlite3"
)

const (
	DbVariableNotSet = -1
	StorageTolerance = 600
	DataLimit        = 10000
)

var (
	cacheLock sync.Mutex
)

type DiskCache struct {
	*cacheData
	storagePeriod int64
	oldestLog     int64
	oldestData    int64
	serverID      int
	database      *sql.DB
	persistFlag   uint32
}

func (c *DiskCache) resultFetch(rows *sql.Rows) (d *AgentData, err error) {
	var tmp uint64
	var LastLogSize int64
	var data AgentData
	var Mtime, State, EventID, EventSeverity, EventTimestamp int
	var Value, EventSource string

	err = rows.Scan(&data.Id, &data.Itemid, &LastLogSize, &Mtime, &State, &Value, &EventSource, &EventID,
		&EventSeverity, &EventTimestamp, &data.Clock, &data.Ns)
	if err == nil {
		if LastLogSize != DbVariableNotSet {
			tmp = uint64(LastLogSize)
			data.LastLogsize = &tmp
		}
		if Mtime != DbVariableNotSet {
			data.Mtime = &Mtime
		}
		if State != DbVariableNotSet {
			data.State = &State
		}
		if Value != "" {
			data.Value = &Value
		}
		if EventSource != "" {
			data.EventSource = &EventSource
		}
		if EventID != DbVariableNotSet {
			data.EventID = &EventID
		}
		if EventSeverity != DbVariableNotSet {
			data.EventSeverity = &EventSeverity
		}
		if EventTimestamp != DbVariableNotSet {
			data.EventTimestamp = &EventTimestamp
		}
	}
	return &data, err
}

func (c *DiskCache) getOldestWriteClock(table string) (clock int64, err error) {
	rows, err := c.database.Query(fmt.Sprintf("SELECT MIN(write_clock) FROM %s", table))
	if err != nil {
		return
	}
	var u interface{}
	ok, err := fetchRowAndClose(rows, &u)
	if err != nil {
		return
	}
	if !ok || u == nil {
		return 0, nil
	}
	clock, ok = u.(int64)
	if !ok {
		c.Warningf("unexpected write clock type %T", u)
		clock = 0
	}
	return
}

func (c *DiskCache) getLastID(table string) (id uint64, err error) {
	rows, err := c.database.Query(fmt.Sprintf("SELECT MAX(id) FROM %s", table))
	if err != nil {
		return
	}
	var u interface{}
	ok, err := fetchRowAndClose(rows, &u)
	if err != nil {
		return
	}
	if !ok || u == nil {
		return 0, nil
	}
	v, ok := u.(int64)
	if !ok {
		c.Warningf("unexpected id type %T", u)
		id = 0
	}
	return uint64(v), nil
}

func (c *DiskCache) updateDataRange() (err error) {
	clock, err := c.getOldestWriteClock(tableName("data", c.serverID))
	if err != nil {
		return
	}
	c.oldestData = clock
	return
}

func (c *DiskCache) updateLogRange() (err error) {
	clock, err := c.getOldestWriteClock(tableName("log", c.serverID))
	if err != nil {
		return
	}
	c.oldestLog = clock
	if c.oldestLog == 0 || time.Now().Unix()-c.oldestLog < c.storagePeriod {
		atomic.StoreUint32(&c.persistFlag, 0)
	}
	return
}

func (c *DiskCache) resultsGet() (results []*AgentData, maxDataId uint64, maxLogId uint64, err error) {
	var result *AgentData
	var rows *sql.Rows

	cacheLock.Lock()
	defer cacheLock.Unlock()

	if rows, err = c.database.Query(fmt.Sprintf("SELECT "+
		"id,itemid,lastlogsize,mtime,state,value,eventsource,eventid,eventseverity,eventtimestamp,clock,ns"+
		" FROM data_%d ORDER BY id LIMIT ?", c.serverID), DataLimit); err != nil {
		c.Errf("cannot select from data table: %s", err.Error())
		return nil, 0, 0, err
	}

	for rows.Next() {
		if result, err = c.resultFetch(rows); err != nil {
			rows.Close()
			return nil, 0, 0, err
		}
		result.persistent = false
		results = append(results, result)
	}
	if err = rows.Err(); err != nil {
		return nil, 0, 0, err
	}
	dataLen := len(results)
	if dataLen != 0 {
		maxDataId = results[dataLen-1].Id
	}

	if dataLen != DataLimit {
		if rows, err = c.database.Query(fmt.Sprintf("SELECT "+
			"id,itemid,lastlogsize,mtime,state,value,eventsource,eventid,eventseverity,eventtimestamp,clock,ns"+
			" FROM log_%d ORDER BY id LIMIT ?", c.serverID), DataLimit-len(results)); err != nil {
			c.Errf("cannot select from log table: %s", err.Error())
			return nil, 0, 0, err
		}
		for rows.Next() {
			if result, err = c.resultFetch(rows); err != nil {
				rows.Close()
				return nil, 0, 0, err
			}
			result.persistent = true
			results = append(results, result)
		}
		if err = rows.Err(); err != nil {
			return nil, 0, 0, err
		}
		if len(results) != dataLen {
			maxLogId = results[len(results)-1].Id
		}
	}

	return results, maxDataId, maxLogId, nil
}

func (c *DiskCache) upload(u Uploader) (err error) {
	var results []*AgentData
	var maxDataId, maxLogId uint64
	var errs []error

	defer func() {
		if nil == err || errs != nil { // report errors not related to Write
			return
		}

		errs = append(errs, err)
		if !reflect.DeepEqual(errs, c.lastErrors) {
			c.Warningf("cannot upload history data: %s", err)
			c.lastErrors = errs
		}
	}()

	if results, maxDataId, maxLogId, err = c.resultsGet(); err != nil {
		return
	}

	if len(results) == 0 {
		return
	}

	request := AgentDataRequest{
		Request: "agent data",
		Data:    results,
		Session: c.token,
		Host:    u.Hostname(),
		Version: version.Short(),
	}

	var data []byte

	if data, err = json.Marshal(&request); err != nil {
		c.Errf("cannot convert cached history to json: %s", err.Error())
		return
	}

	timeout := len(results) * c.timeout
	if timeout > 60 {
		timeout = 60
	}
	if errs = u.Write(data, time.Duration(timeout)*time.Second); errs != nil {
		if !reflect.DeepEqual(errs, c.lastErrors) {
			for i := 0; i < len(errs); i++ {
				c.Warningf("%s", errs[i])
			}
			c.Warningf("history upload to [%s] [%s] started to fail", u.Addr(), u.Hostname())
			c.lastErrors = errs
		}
		err = errors.New("history upload failed")
		return
	}

	if c.lastErrors != nil {
		c.Warningf("history upload to [%s] [%s] is working again", u.Addr(), u.Hostname())
		c.lastErrors = nil
	}
	cacheLock.Lock()
	defer cacheLock.Unlock()
	if maxDataId != 0 {
		if _, err = c.database.Exec(fmt.Sprintf("DELETE FROM data_%d WHERE id<=?", c.serverID), maxDataId); err != nil {
			return fmt.Errorf("cannot delete from data_%d: %s", c.serverID, err)
		}
		if err = c.updateDataRange(); err != nil {
			return
		}
	}
	if maxLogId != 0 {
		if _, err = c.database.Exec(fmt.Sprintf("DELETE FROM log_%d WHERE id<=?", c.serverID), maxLogId); err != nil {
			return fmt.Errorf("cannot delete from log_%d: %s", c.serverID, err)
		}
		if err = c.updateLogRange(); err != nil {
			return
		}
	}

	return
}

func (c *DiskCache) flushOutput(u Uploader) {
	if c.retry != nil {
		c.retry.Stop()
		c.retry = nil
	}

	if err := c.upload(u); err != nil && u.CanRetry() {
		c.retry = time.AfterFunc(UploadRetryInterval, func() { c.Upload(u) })
	}
}

func (c *DiskCache) write(r *plugin.Result) {
	var err error
	c.lastDataID++

	var LastLogsize int64 = DbVariableNotSet
	if r.LastLogsize != nil {
		LastLogsize = int64(*r.LastLogsize)
	}

	var Value string
	var State int = DbVariableNotSet
	if r.Error != nil {
		Value = r.Error.Error()
		State = itemutil.StateNotSupported
	} else if r.Value != nil {
		Value = *r.Value
	}

	var ns int
	var clock int64
	if !r.Ts.IsZero() {
		clock = r.Ts.Unix()
		ns = r.Ts.Nanosecond()
	}

	var Mtime int = DbVariableNotSet
	if r.Mtime != nil {
		Mtime = *r.Mtime
	}

	var EventSource string
	if r.EventSource != nil {
		EventSource = *r.EventSource
	}

	var EventID int = DbVariableNotSet
	if r.EventID != nil {
		EventID = *r.EventID
	}

	var EventSeverity int = DbVariableNotSet
	if r.EventSeverity != nil {
		EventSeverity = *r.EventSeverity
	}

	var EventTimestamp int = DbVariableNotSet
	if r.EventTimestamp != nil {
		EventTimestamp = *r.EventTimestamp
	}

	var stmt *sql.Stmt

	now := time.Now().Unix()
	cacheLock.Lock()
	defer cacheLock.Unlock()

	if r.Persistent {

		if c.oldestLog == 0 {
			c.oldestLog = clock
		}
		if (now - c.oldestLog) > c.storagePeriod {
			atomic.StoreUint32(&c.persistFlag, 1)
		}
		stmt, err = c.database.Prepare(c.insertResultTable(fmt.Sprintf("log_%d", c.serverID)))

		if err != nil {
			c.Errf("cannot prepare SQL query to insert history in log_%d : %s", c.serverID, err)
		} else {
			defer stmt.Close()
		}

	} else {
		if c.oldestData == 0 {
			c.oldestData = clock
		}

		if (now - c.oldestData) > c.storagePeriod+StorageTolerance {
			query := fmt.Sprintf("DELETE FROM data_%d WHERE clock<?", c.serverID)
			if _, err = c.database.Exec(query, now-c.storagePeriod); err != nil {
				c.Errf("cannot delete old data from data_%d : %s", c.serverID, err)
			}

			c.oldestData, err = c.getOldestWriteClock(tableName("data", c.serverID))
			if err != nil {
				c.Errf("cannot query minimum write clock from data_%d : %s", c.serverID, err)
			}
		}
		stmt, err = c.database.Prepare(c.insertResultTable(fmt.Sprintf("data_%d", c.serverID)))
		if err != nil {
			c.Errf("cannot prepare SQL query to insert history in data_%d : %s", c.serverID, err)
		} else {
			defer stmt.Close()
		}
	}
	if stmt != nil {
		_, err = stmt.Exec(c.lastDataID, now, r.Itemid, LastLogsize, Mtime, State, Value,
			EventSource, EventID, EventSeverity, EventTimestamp, clock, ns)
		if err != nil {
			c.Errf("cannot execute SQL statement : %s", err)
		}
	}
	if err != nil {
		panic(err)
	}
}

func (c *DiskCache) run() {
	defer log.PanicHook()
	c.Debugf("starting disk cache")

	for {
		u := <-c.input
		if u == nil {
			break
		}
		switch v := u.(type) {
		case Uploader:
			c.flushOutput(v)
		case *plugin.Result:
			c.write(v)
		case *agent.AgentOptions:
			c.updateOptions(v)
		}
	}
	c.Debugf("disk cache has been stopped")
	if c.database != nil {
		c.database.Close()
	}
	monitor.Unregister(monitor.Output)
}

func (c *DiskCache) updateOptions(options *agent.AgentOptions) {
	c.storagePeriod = int64(options.PersistentBufferPeriod)
	c.timeout = options.Timeout
}

func (c *DiskCache) insertResultTable(table string) string {
	return fmt.Sprintf(
		"INSERT INTO %s"+
			"(id,write_clock,itemid,lastlogsize,mtime,state,value,eventsource,eventid,eventseverity,eventtimestamp,clock,ns)"+
			"VALUES"+
			"(?,?,?,?,?,?,?,?,?,?,?,?,?)",
		table)
}

func (c *DiskCache) init(options *agent.AgentOptions) {
	c.updateOptions(options)

	var err error
	cacheLock.Lock()
	defer cacheLock.Unlock()

	c.database, err = sql.Open("sqlite3", agent.Options.PersistentBufferFile)
	if err != nil {
		return
	}

	rows, err := c.database.Query(fmt.Sprintf("SELECT "+
		"id FROM registry WHERE address = '%s' AND hostname = '%s'", c.uploader.Addr(), c.uploader.Hostname()))

	if err == nil {
		defer rows.Close()

		for rows.Next() {
			if err = rows.Scan(&c.serverID); err != nil {
				c.Errf("cannot retrieve diskcache server ID ")
				c.serverID = 0
			}
		}
	}

	/* log history is removed before disk cache is initialized, so only data history needs to be checked */
	c.lastDataID, err = c.getLastID(tableName("data", c.serverID))
	if err != nil {
		c.Errf("cannot obtain last data record ID")
	}
	if err = c.updateDataRange(); err != nil {
		c.Errf("cannot update data clock")
	}
}

func (c *DiskCache) Start() {
	// register with secondary group to stop result cache after other components are stopped
	monitor.Register(monitor.Output)
	go c.run()
}

func (c *DiskCache) SlotsAvailable() int {
	return int(^uint(0) >> 1) //Max int
}

func (c *DiskCache) PersistSlotsAvailable() int {

	if atomic.LoadUint32(&c.persistFlag) == 1 {
		return 0
	}
	return int(^uint(0) >> 1) //Max int
}
