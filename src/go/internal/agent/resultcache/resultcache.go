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
	"encoding/json"
	"errors"
	"fmt"
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
	UploadRetryInterval = time.Second
	DbVariableNotSet    = -1
)

var database *sql.DB

type ResultCache interface {
	Start()
	Stop()
	UpdateOptions(options *agent.AgentOptions)
	Upload(u Uploader)
}

type MemoryCache struct {
	input           chan interface{}
	output          Uploader
	results         []*AgentData
	token           string
	lastDataID      uint64
	clientID        uint64
	lastError       error
	maxBufferSize   int32
	totalValueNum   int32
	persistValueNum int32
	retry           *time.Timer
	timeout         int
}

type PersistCache struct {
	input         chan interface{}
	output        Uploader
	results       []*AgentData
	token         string
	lastDataID    uint64
	clientID      uint64
	lastError     error
	retry         *time.Timer
	timeout       int
	persistPeriod int
	oldestLog     uint64
	oldestData    uint64
	connectId     int
	logTable      string
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

func (c *MemoryCache) upload(u Uploader) (err error) {
	if len(c.results) == 0 {
		return
	}

	log.Debugf("[%d] upload history data, %d/%d value(s)", c.clientID, len(c.results), cap(c.results))

	request := AgentDataRequest{
		Request: "agent data",
		Data:    c.results,
		Session: c.token,
		Host:    agent.Options.Hostname,
		Version: version.Short(),
	}

	var data []byte

	if data, err = json.Marshal(&request); err != nil {
		log.Errf("[%d] cannot convert cached history to json: %s", c.clientID, err.Error())
		return
	}

	timeout := len(c.results) * c.timeout
	if timeout > 60 {
		timeout = 60
	}
	if err = u.Write(data, time.Duration(timeout)*time.Second); err != nil {
		if c.lastError == nil || err.Error() != c.lastError.Error() {
			log.Warningf("[%d] history upload to [%s] started to fail: %s", c.clientID, u.Addr(), err)
			c.lastError = err
		}
		return
	}

	if c.lastError != nil {
		log.Warningf("[%d] history upload to [%s] is working again", c.clientID, u.Addr())
		c.lastError = nil
	}

	// clear results slice to ensure that the data is garbage collected
	c.results[0] = nil
	for i := 1; i < len(c.results); i *= 2 {
		copy(c.results[i:], c.results[:i])
	}
	c.results = c.results[:0]

	c.totalValueNum = 0
	c.persistValueNum = 0
	return
}

func (c *PersistCache) resultFetch(rows *sql.Rows) AgentData {
	var tmp uint64
	var data AgentData
	var LastLogSize int64
	var Mtime, State, EventID, EventSeverity, EventTimestamp int
	var Value, EventSource string

	rows.Scan(&data.Id, &data.Itemid, &LastLogSize, &Mtime, &State, &Value, &EventSource, &EventID,
		&EventSeverity, &EventTimestamp, &data.Clock, &data.Ns)
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
	return data
}

func (c *PersistCache) upload(u Uploader) (err error) {

	var results []*AgentData
	var rows *sql.Rows

	if rows, err = database.Query(fmt.Sprintf("SELECT * FROM data_%d", c.connectId)); err != nil {
		log.Errf("[%d] cannot select from data table: %s", c.clientID, err.Error())
		return
	}
	for rows.Next() {
		result := c.resultFetch(rows)
		result.persistent = false
		results = append(results, &result)

	}
	if rows, err = database.Query(fmt.Sprintf("SELECT * FROM log_%d", c.connectId)); err != nil {
		log.Errf("[%d] cannot select from log table: %s", c.clientID, err.Error())
		return
	}
	for rows.Next() {
		result := c.resultFetch(rows)
		result.persistent = true
		results = append(results, &result)
	}
	if len(results) == 0 {
		return
	}

	request := AgentDataRequest{
		Request: "agent data",
		Data:    results,
		Session: c.token,
		Host:    agent.Options.Hostname,
		Version: version.Short(),
	}

	var data []byte

	if data, err = json.Marshal(&request); err != nil {
		log.Errf("[%d] cannot convert cached history to json: %s", c.clientID, err.Error())
		return
	}

	timeout := len(results) * c.timeout
	if timeout > 60 {
		timeout = 60
	}
	if err = u.Write(data, time.Duration(timeout)*time.Second); err != nil {
		if c.lastError == nil || err.Error() != c.lastError.Error() {
			log.Warningf("[%d] history upload to [%s] started to fail: %s", c.clientID, u.Addr(), err)
			c.lastError = err
		}
		return
	}

	if c.lastError != nil {
		log.Warningf("[%d] history upload to [%s] is working again", c.clientID, u.Addr())
		c.lastError = nil
	}
	database.Exec(fmt.Sprintf("DELETE FROM data_%d", c.connectId))
	database.Exec(fmt.Sprintf("DELETE FROM log_%d", c.connectId))
	c.oldestData = 0
	c.oldestLog = 0

	return
}

func (c *MemoryCache) flushOutput(u Uploader) {
	if c.retry != nil {
		c.retry.Stop()
		c.retry = nil
	}

	if c.upload(u) != nil && u.CanRetry() {
		c.retry = time.AfterFunc(UploadRetryInterval, func() { c.Upload(u) })
	}
}

func (c *PersistCache) flushOutput(u Uploader) {
	if c.retry != nil {
		c.retry.Stop()
		c.retry = nil
	}

	if c.upload(u) != nil && u.CanRetry() {
		c.retry = time.AfterFunc(UploadRetryInterval, func() { c.Upload(u) })
	}
}

// addResult appends received result at the end of results slice
func (c *MemoryCache) addResult(result *AgentData) {
	full := c.persistValueNum >= c.maxBufferSize/2 || c.totalValueNum >= c.maxBufferSize
	c.results = append(c.results, result)
	c.totalValueNum++
	if result.persistent {
		c.persistValueNum++
	}

	if c.persistValueNum >= c.maxBufferSize/2 || c.totalValueNum >= c.maxBufferSize {
		if !full && c.output != nil {
			c.flushOutput(c.output)
		}
	}
}

// insertResult attempts to insert the received result into results slice by replacing existing value.
// If no appropriate target was found it calls addResult to append value.
func (c *MemoryCache) insertResult(result *AgentData) {
	index := -1
	if !result.persistent {
		for i, r := range c.results {
			if r.Itemid == result.Itemid {
				log.Debugf("[%d] cache is full, replacing oldest value for itemid:%d", c.clientID, r.Itemid)
				index = i
				break
			}
		}
	}
	if index == -1 && (!result.persistent || c.persistValueNum < c.maxBufferSize/2) {
		for i, r := range c.results {
			if !r.persistent {
				if result.persistent {
					c.persistValueNum++
				}
				log.Debugf("[%d] cache is full, removing oldest value for itemid:%d", c.clientID, r.Itemid)
				index = i
				break
			}
		}
	}
	if index == -1 {
		log.Warningf("[%d] cache is full and cannot cannot find a value to replace, adding new instead", c.clientID)
		c.addResult(result)
		return
	}

	copy(c.results[index:], c.results[index+1:])
	c.results[len(c.results)-1] = result
}

func (c *MemoryCache) write(r *plugin.Result) {
	c.lastDataID++
	var value *string
	var state *int
	if r.Error == nil {
		value = r.Value
	} else {
		errmsg := r.Error.Error()
		value = &errmsg
		tmp := itemutil.StateNotSupported
		state = &tmp
	}

	var clock, ns int
	if !r.Ts.IsZero() {
		clock = int(r.Ts.Unix())
		ns = r.Ts.Nanosecond()
	}

	data := &AgentData{
		Id:             c.lastDataID,
		Itemid:         r.Itemid,
		LastLogsize:    r.LastLogsize,
		Mtime:          r.Mtime,
		Clock:          clock,
		Ns:             ns,
		Value:          value,
		State:          state,
		EventSource:    r.EventSource,
		EventID:        r.EventID,
		EventSeverity:  r.EventSeverity,
		EventTimestamp: r.EventTimestamp,
		persistent:     r.Persistent,
	}

	if c.totalValueNum >= c.maxBufferSize {
		c.insertResult(data)
	} else {
		c.addResult(data)
	}
}

func (c *PersistCache) write(r *plugin.Result) {
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
	var clock uint64
	if !r.Ts.IsZero() {
		clock = uint64(r.Ts.Unix())
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

	if r.Persistent == true {
		if c.oldestLog == 0 {
			c.oldestLog = clock
		}
		if (clock - c.oldestLog) <= uint64(c.persistPeriod) {
			stmt, _ = database.Prepare(c.InsertResultTable(fmt.Sprintf("log_%d", c.connectId)))
		}
	} else {
		if c.oldestData == 0 {
			c.oldestData = clock
		}
		if (clock - c.oldestData) > uint64(c.persistPeriod) {
			query := fmt.Sprintf("DELETE FROM data_%d WHERE clock = %d", c.connectId, c.oldestData)
			database.Exec(query)
			rows, err := database.Query(fmt.Sprintf("SELECT MIN(Clock) FROM data_%d", c.connectId))
			if err != nil {
				for rows.Next() {
					rows.Scan(&c.oldestData)
				}
			}
		}
		stmt, _ = database.Prepare(c.InsertResultTable(fmt.Sprintf("data_%d", c.connectId)))
	}
	if stmt != nil {
		stmt.Exec(c.lastDataID, r.Itemid, LastLogsize, Mtime, State, Value,
			EventSource, EventID, EventSeverity, EventTimestamp, clock, ns)
	}

}

func (c *MemoryCache) run() {
	defer log.PanicHook()
	log.Debugf("[%d] starting memory cache", c.clientID)

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
	log.Debugf("[%d] memory cache has been stopped", c.clientID)
	monitor.Unregister(monitor.Output)
}

func (c *PersistCache) run() {
	defer log.PanicHook()
	log.Debugf("[%d] starting persistent cache", c.clientID)

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
	log.Debugf("[%d] memory cache has been stopped", c.clientID)
	monitor.Unregister(monitor.Output)
}

func newToken() string {
	h := md5.New()
	_ = binary.Write(h, binary.LittleEndian, time.Now().UnixNano())
	return hex.EncodeToString(h.Sum(nil))
}

func (c *MemoryCache) updateOptions(options *agent.AgentOptions) {
	c.maxBufferSize = int32(options.BufferSize)
	c.timeout = options.Timeout
}

func (c *PersistCache) updateOptions(options *agent.AgentOptions) {
	c.persistPeriod = options.PersistentBufferPeriod
}

func (c *PersistCache) InsertResultTable(table string) string {
	return fmt.Sprintf(`
		INSERT INTO %s
		(Id, Itemid, LastLogsize, Mtime, State, Value, EventSource, EventID, EventSeverity, EventTimestamp, Clock, Ns)
		VALUES
		(?,?,?,?,?,?,?,?,?,?,?,?)	
	`, table)
}

func (c *MemoryCache) init() {
	c.updateOptions(&agent.Options)
	c.input = make(chan interface{}, 100)
	c.results = make([]*AgentData, 0, c.maxBufferSize)
}

func (c *PersistCache) init() {
	c.updateOptions(&agent.Options)
	c.input = make(chan interface{}, 100)

	rows, err := database.Query(fmt.Sprintf("SELECT Id FROM registry WHERE Address = '%s'", c.output.Addr()))
	if err == nil {
		for rows.Next() {
			rows.Scan(&c.connectId)
		}
	}

	if rows, err = database.Query(fmt.Sprintf("SELECT MIN(Clock), MAX(Id) FROM data_%d", c.connectId)); err == nil {
		for rows.Next() {
			rows.Scan(&c.oldestData, &c.lastDataID)
		}
	}
}

func (c *MemoryCache) Start() {
	// register with secondary group to stop result cache after other components are stopped
	monitor.Register(monitor.Output)
	go c.run()
}

func (c *PersistCache) Start() {
	// register with secondary group to stop result cache after other components are stopped
	monitor.Register(monitor.Output)
	go c.run()
}

func (c *MemoryCache) Stop() {
	c.input <- nil
}

func (c *PersistCache) Stop() {
	c.input <- nil
}

func New(options *agent.AgentOptions, clientid uint64, output Uploader) ResultCache {
	var cache ResultCache
	if options.EnablePersistentBuffer == 0 {
		cache = &MemoryCache{clientID: clientid, output: output, token: newToken()}
		cache.(*MemoryCache).init()

	} else {
		cache = &PersistCache{clientID: clientid, output: output, token: newToken()}
		cache.(*PersistCache).init()
	}

	return cache
}

func (c *MemoryCache) Upload(u Uploader) {
	Uploader := u
	if u == nil {
		Uploader = c.output
	}
	// only active connections with output set can be flushed without specifying output
	if Uploader != nil {
		c.input <- Uploader
	}
}

func (c *PersistCache) Upload(u Uploader) {
	Uploader := u
	if u == nil {
		Uploader = c.output
	}
	// only active connections with output set can be flushed without specifying output
	if Uploader != nil {
		c.input <- Uploader
	}
}

func (c *MemoryCache) Flush() {
	c.Upload(c.output)
}

func (c *PersistCache) Flush() {
	c.Upload(c.output)
}

func (c *MemoryCache) Write(result *plugin.Result) {
	c.input <- result
}

func (c *PersistCache) Write(result *plugin.Result) {
	c.input <- result
}

func (c *MemoryCache) UpdateOptions(options *agent.AgentOptions) {
	c.input <- options
}

func (c *PersistCache) UpdateOptions(options *agent.AgentOptions) {
	c.input <- options
}

func (c *MemoryCache) SlotsAvailable() int {
	slots := atomic.LoadInt32(&c.maxBufferSize) - atomic.LoadInt32(&c.totalValueNum)
	if slots < 0 {
		slots = 0
	}

	return int(slots)
}

func (c *PersistCache) SlotsAvailable() int {
	return int(^uint(0) >> 1) //Max int
}

func (c *MemoryCache) PersistSlotsAvailable() int {
	slots := atomic.LoadInt32(&c.maxBufferSize)/2 - atomic.LoadInt32(&c.persistValueNum)
	if slots < 0 {
		slots = 0
	}
	return int(slots)
}

func (c *PersistCache) PersistSlotsAvailable() int {
	return int(^uint(0) >> 1) //Max int
}

func CheckCacheConfiguration(options *agent.AgentOptions) (err error) {
	/*if options.EnablePersistentBuffer == 1 && options.BufferSize != 0 {
		return errors.New("\"BufferSize\" parameter is set")
	}*/
	if options.EnablePersistentBuffer == 1 && options.PersistentBufferFile == "" {
		return errors.New("\"PersistentBufferFile\" parameter is not set")
	}
	if options.EnablePersistentBuffer == 0 && options.PersistentBufferFile != "" {
		log.Warningf("\"PersistentBufferFile\" parameter is not empty but \"EnablePersistentBuffer\" is not set")
	}
	return err

}

func CacheConfiguration(options *agent.AgentOptions, addresses []string) (err error) {
	if options.EnablePersistentBuffer == 0 {
		return err
	}
	database, err = sql.Open("sqlite3", options.PersistentBufferFile)
	if err != nil {
		return fmt.Errorf("Cannot open database %s.", options.PersistentBufferFile)
	}
	stmt, _ := database.Prepare("CREATE TABLE IF NOT EXISTS registry (Id INTEGER PRIMARY KEY, Address TEXT, UNIQUE(Address) )")
	stmt.Exec()

	var Id, Found, i int
	var Address string
	Ids := make([]int, 0)
	Addresses := make([]string, 0)
	rows, err := database.Query("SELECT Id, Address FROM registry")
	if err != nil {
		return err
	}
	for rows.Next() {
		rows.Scan(&Id, &Address)
		Ids = append(Ids, Id)
		Addresses = append(Addresses, Address)
	}
	for i, Address = range Addresses {
		for _, addr := range addresses {
			if addr == Address {
				Found = 1
				break
			}
		}
		if Found == 0 {
			database.Exec(fmt.Sprintf("DELETE FROM registry WHERE ID = %d", Ids[i]))
			database.Exec(fmt.Sprintf("DROP TABLE data_%d", Ids[i]))
			database.Exec(fmt.Sprintf("DROP TABLE log_%d", Ids[i]))
		}
		Found = 0
	}

	CreateTable := func(table string) string {
		return fmt.Sprintf(`
			CREATE TABLE IF NOT EXISTS %s
			(Id INTEGER,
			Itemid INTEGER,
			LastLogsize INTEGER,
			Mtime INTEGER,
			State INTEGER,
			Value TEXT,
			EventSource TEXT,
			EventID INTEGER,
			EventSeverity INTEGER,
			EventTimestamp INTEGER,
			Clock INTEGER,
			Ns INTEGER
			)
		`, table)
	}
	for _, addr := range addresses {
		stmt, err = database.Prepare("INSERT OR IGNORE INTO registry (Address) VALUES (?)")
		if err != nil {
			break
		}
		stmt.Exec(addr)
		rows, err = database.Query(fmt.Sprintf("SELECT Id FROM registry WHERE Address = '%s'", addr))
		if err != nil {
			break
		}
		for rows.Next() {
			rows.Scan(&Id)
		}
		stmt, err = database.Prepare(CreateTable(fmt.Sprintf("data_%d", Id)))
		if err != nil {
			break
		}
		stmt.Exec()
		stmt, err = database.Prepare(CreateTable(fmt.Sprintf("log_%d", Id)))
		if err != nil {
			break
		}
		stmt.Exec()
		database.Exec(fmt.Sprintf("DELETE FROM log_%d", Id))

	}
	return err

}

func CacheClose(options *agent.AgentOptions) {
	if options.EnablePersistentBuffer != 0 {
		database.Close()
	}
}
