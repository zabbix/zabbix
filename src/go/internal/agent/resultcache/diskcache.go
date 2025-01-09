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

	_ "github.com/mattn/go-sqlite3"
	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/internal/monitor"
	"golang.zabbix.com/agent2/pkg/itemutil"
	"golang.zabbix.com/agent2/pkg/version"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin"
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
	oldestCommand int64
	serverID      int
	database      *sql.DB
	persistFlag   uint32
	historyUpload bool
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

func (c *DiskCache) updateCommandRange() (err error) {
	clock, err := c.getOldestWriteClock(tableName("command", c.serverID))
	if err != nil {
		return
	}
	c.oldestCommand = clock

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
		" FROM data_%d"+
		" UNION ALL"+
		" SELECT "+
		"id,itemid,lastlogsize,mtime,state,value,eventsource,eventid,eventseverity,eventtimestamp,clock,ns"+
		" FROM log_%d"+
		" ORDER BY id LIMIT ?", c.serverID, c.serverID), DataLimit); err != nil {
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
		if result.LastLogsize == nil {
			maxDataId = result.Id
		} else {
			maxLogId = result.Id
		}
	}
	if err = rows.Err(); err != nil {
		return nil, 0, 0, err
	}

	return results, maxDataId, maxLogId, nil
}

func (c *DiskCache) commandResultsGet() (results []*AgentCommands, maxCommandId uint64, err error) {
	var result AgentCommands
	var rows *sql.Rows
	var Value, ErrMsg string
	var id uint64

	cacheLock.Lock()
	defer cacheLock.Unlock()

	if rows, err = c.database.Query(fmt.Sprintf("SELECT "+
		"id,cmd_id,value,error"+
		" FROM command_%d"+
		" ORDER BY id LIMIT ?", c.serverID), DataLimit); err != nil {
		c.Errf("cannot select from command table: %s", err.Error())

		return nil, 0, err
	}

	for rows.Next() {
		err = rows.Scan(&id, &result.Id, &Value, &ErrMsg)
		if err == nil {
			if Value != "" {
				result.Value = &Value
			}
			if ErrMsg != "" {
				result.Error = &ErrMsg
			}
		} else {
			rows.Close()
			_ = rows.Err()

			return nil, 0, err
		}
		results = append(results, &result)
		maxCommandId = id
	}

	return results, maxCommandId, nil
}

func (c *DiskCache) upload(u Uploader) (err error) {
	var results []*AgentData
	var cresults []*AgentCommands
	var maxDataId, maxLogId, maxCommandId uint64
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

	if cresults, maxCommandId, err = c.commandResultsGet(); err != nil {
		return
	}

	reqLen := len(results) + len(cresults)

	if reqLen == 0 {
		return
	}

	request := AgentDataRequest{
		Request:  "agent data",
		Data:     results,
		Commands: cresults,
		Session:  u.Session(),
		Host:     u.Hostname(),
		Version:  version.Long(),
		Variant:  agent.Variant,
	}

	var data []byte

	if data, err = json.Marshal(&request); err != nil {
		c.Errf("cannot convert cached history to json: %s", err.Error())

		return
	}

	timeout := reqLen * c.timeout
	if timeout > 60 {
		timeout = 60
	}
	var upload bool

	if upload, errs = u.Write(data, time.Duration(timeout)*time.Second); errs != nil {
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

	c.EnableUpload(upload)

	if c.lastErrors != nil {
		c.Warningf("history upload to [%s] [%s] is working again", u.Addr(), u.Hostname())
		c.lastErrors = nil
	}
	cacheLock.Lock()
	defer cacheLock.Unlock()
	if maxDataId != 0 {
		if _, err = c.database.Exec(fmt.Sprintf("DELETE FROM data_%d WHERE id<=?", c.serverID), maxDataId); err != nil {
			return fmt.Errorf("cannot delete from data_%d: %w", c.serverID, err)
		}
		if err = c.updateDataRange(); err != nil {
			return
		}
	}
	if maxLogId != 0 {
		if _, err = c.database.Exec(fmt.Sprintf("DELETE FROM log_%d WHERE id<=?", c.serverID), maxLogId); err != nil {
			return fmt.Errorf("cannot delete from log_%d: %w", c.serverID, err)
		}
		if err = c.updateLogRange(); err != nil {
			return
		}
	}
	if maxCommandId != 0 {
		if _, err = c.database.Exec(fmt.Sprintf("DELETE FROM command_%d WHERE id<=?", c.serverID), maxCommandId); err != nil {
			return fmt.Errorf("cannot delete from command_%d: %w", c.serverID, err)
		}
		if err = c.updateCommandRange(); err != nil {
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

func (c *DiskCache) writeCommand(cr *CommandResult) {
	var err error

	log.Debugf("cache command(%d) result:%s error:%s", cr.ID, cr.Result, cr.Error)
	c.lastCommandID++

	var ErrMsg string
	if cr.Error != nil {
		ErrMsg = cr.Error.Error()
	}

	var stmt *sql.Stmt

	now := time.Now().Unix()
	cacheLock.Lock()
	defer cacheLock.Unlock()

	if c.oldestCommand == 0 {
		c.oldestCommand = now
	}

	if (now - c.oldestCommand) > c.storagePeriod+StorageTolerance {
		query := fmt.Sprintf("DELETE FROM command_%d WHERE write_clock<?", c.serverID)
		if _, err = c.database.Exec(query, now-c.storagePeriod); err != nil {
			c.Errf("cannot delete old commands from command_%d : %s", c.serverID, err)
		}

		c.oldestCommand, err = c.getOldestWriteClock(tableName("command", c.serverID))
		if err != nil {
			c.Errf("cannot query minimum write clock from command_%d : %s", c.serverID, err)
		}
	}
	stmt, err = c.database.Prepare(fmt.Sprintf(
		"INSERT INTO command_%d"+
			"(id,write_clock,cmd_id,value,error)"+
			"VALUES"+
			"(?,?,?,?,?)", c.serverID))
	if err != nil {
		c.Errf("cannot prepare SQL query to insert commands in command_%d : %s", c.serverID, err)
	} else {
		defer stmt.Close()
	}

	if stmt != nil {
		_, err = stmt.Exec(c.lastCommandID, now, cr.ID, cr.Result, ErrMsg)
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
		case *CommandResult:
			c.writeCommand(v)
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

	c.lastCommandID, err = c.getLastID(tableName("command", c.serverID))
	if err != nil {
		c.Errf("cannot obtain last command record ID")
	}
	if err = c.updateCommandRange(); err != nil {
		c.Errf("cannot update command clock")
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
