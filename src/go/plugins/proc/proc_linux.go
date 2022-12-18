//go:build linux
// +build linux

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

package proc

/*
#include <unistd.h>
*/
import "C"

import (
	"errors"
	"fmt"
	"math"
	"os/user"
	"regexp"
	"strconv"
	"sync"
	"time"

	"zabbix.com/pkg/log"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/procfs"
)

const (
	maxInactivityPeriod = time.Hour * 25
	maxHistory          = 60*15 + 1
)

// Plugin -
type Plugin struct {
	plugin.Base
	queries map[procQuery]*cpuUtilStats
	mutex   sync.Mutex
	scanid  uint64
	stats   map[int64]*cpuUtil
}

type PluginExport struct {
	plugin.Base
}

var impl Plugin = Plugin{
	stats:   make(map[int64]*cpuUtil),
	queries: make(map[procQuery]*cpuUtilStats),
}

var implExport PluginExport = PluginExport{}

type historyIndex int

func (h historyIndex) inc() historyIndex {
	h++
	if h == maxHistory {
		h = 0
	}
	return h
}

func (h historyIndex) dec() historyIndex {
	h--
	if h < 0 {
		h = maxHistory - 1
	}
	return h
}

func (h historyIndex) sub(value historyIndex) historyIndex {
	h -= value
	for h < 0 {
		h += maxHistory
	}
	return h
}

type cpuUtilData struct {
	utime     uint64
	stime     uint64
	timestamp time.Time
}

type cpuUtilStats struct {
	scanid         uint64
	accessed       time.Time
	err            error
	cmdlinePattern *regexp.Regexp
	history        []cpuUtilData
	tail           historyIndex
	head           historyIndex
}

type cpuUtilQuery struct {
	procQuery
	userid         int64
	pids           []int64
	cmdlinePattern *regexp.Regexp
	utime          uint64
	stime          uint64
}

type procQuery struct {
	name    string
	user    string
	cmdline string
	state   string
}

const (
	procInfoPid = 1 << iota
	procInfoName
	procInfoUser
	procInfoCmdline
	procInfoState
)

type procInfo struct {
	pid     int64
	name    string
	userid  int64
	cmdline string
	arg0    string
	state   string
}

type cpuUtil struct {
	utime   uint64
	stime   uint64
	started uint64
	err     error
}

func (q *cpuUtilQuery) match(p *procInfo) bool {
	if q.name != "" && q.name != p.name && q.name != p.arg0 {
		return false
	}
	if q.user != "" && q.userid != p.userid {
		return false
	}
	if q.cmdline != "" && !q.cmdlinePattern.Match([]byte(p.cmdline)) {
		return false
	}
	return true
}

func newCpuUtilQuery(q *procQuery, pattern *regexp.Regexp) (query *cpuUtilQuery, err error) {
	query = &cpuUtilQuery{procQuery: *q}
	if q.user != "" {
		var u *user.User
		if u, err = user.Lookup(q.user); err != nil {
			return
		}
		if query.userid, err = strconv.ParseInt(u.Uid, 10, 64); err != nil {
			return
		}
	}

	query.cmdlinePattern = pattern
	return
}

func (p *Plugin) prepareQueries() (queries []*cpuUtilQuery, flags int) {
	now := time.Now()
	flags = procInfoPid

	queries = make([]*cpuUtilQuery, 0, len(p.queries))
	p.mutex.Lock()
	for q, stats := range p.queries {
		if now.Sub(stats.accessed) > maxInactivityPeriod {
			p.Debugf("removed unused CPU utilization query %+v", q)
			delete(p.queries, q)
			continue
		}
		var query *cpuUtilQuery
		if query, stats.err = newCpuUtilQuery(&q, stats.cmdlinePattern); stats.err != nil {
			p.Debugf("cannot create CPU utilization query %+v: %s", q, stats.err)
			continue
		}
		queries = append(queries, query)
		stats.scanid = p.scanid
		if q.name != "" {
			flags |= procInfoName | procInfoCmdline
		}
		if q.user != "" {
			flags |= procInfoUser
		}
		if q.cmdline != "" {
			flags |= procInfoCmdline
		}
	}
	p.mutex.Unlock()
	return
}

func (p *Plugin) Collect() (err error) {
	if log.CheckLogLevel(log.Trace) {
		p.Tracef("In %s() queries:%d", log.Caller(), len(p.queries))
		defer p.Tracef("End of %s()", log.Caller())
	}
	p.scanid++
	queries, flags := p.prepareQueries()
	var processes []*procInfo
	if processes, err = getProcesses(flags); err != nil {
		return
	}
	p.Tracef("%s() queries:%d", log.Caller(), len(p.queries))

	stats := make(map[int64]*cpuUtil)
	// find processes matching prepared queries
	for _, p := range processes {
		var monitored bool
		for _, q := range queries {
			if q.match(p) {
				q.pids = append(q.pids, p.pid)
				monitored = true
			}
		}
		if monitored {
			stats[p.pid] = &cpuUtil{}
		}
	}

	if log.CheckLogLevel(log.Trace) {
		for _, q := range queries {
			p.Tracef("%s() name:%s user:%s cmdline:%s pids:%v", log.Caller(), q.name, q.user, q.cmdline, q.pids)
		}
	}

	now := time.Now()
	for pid, stat := range stats {
		p.getProcCpuUtil(pid, stat)
		if stat.err != nil {
			p.Debugf("cannot get process %d CPU utilization statistics: %s", pid, stat.err)
		}
	}

	// gather process utilization for queries
	for _, q := range queries {
		for _, pid := range q.pids {
			var stat, last *cpuUtil
			var ok bool
			if stat, ok = stats[pid]; !ok || stat.err != nil {
				continue
			}
			if last, ok = p.stats[pid]; !ok || stat.err != nil {
				continue
			}
			if stat.started == last.started {
				q.utime += stat.utime - last.utime
				q.stime += stat.stime - last.stime
			}
		}
	}

	p.stats = stats

	// update statistics
	p.Tracef("%s() update statistics", log.Caller())
	p.mutex.Lock()
	for _, q := range queries {
		if stat, ok := p.queries[q.procQuery]; ok {
			if stat.scanid != p.scanid {
				continue
			}
			var last *cpuUtilData
			if stat.tail != stat.head {
				last = &stat.history[stat.tail.dec()]
			}
			slot := &stat.history[stat.tail]
			slot.utime = q.utime
			slot.stime = q.stime
			slot.timestamp = now
			if last != nil {
				slot.utime += last.utime
				slot.stime += last.stime
			}
			stat.tail = stat.tail.inc()
			if stat.tail == stat.head {
				stat.head = stat.head.inc()
			}
		}
	}
	p.mutex.Unlock()

	return nil
}

func (p *Plugin) Period() int {
	return 1
}

const (
	timeUser = 1 << iota
	timeSystem
	timeTotal = timeUser | timeSystem
)

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if ctx == nil {
		return nil, errors.New("This item is available only in daemon mode.")
	}

	var name, user, cmdline, mode, utiltype string
	switch len(params) {
	case 5:
		mode = params[4]
		fallthrough
	case 4:
		cmdline = params[3]
		fallthrough
	case 3:
		utiltype = params[2]
		fallthrough
	case 2:
		user = params[1]
		fallthrough
	case 1:
		name = params[0]
	case 0:
	default:
		return nil, errors.New("Too many parameters.")
	}

	var utilrange historyIndex
	switch mode {
	case "avg1", "":
		utilrange = 60
	case "avg5":
		utilrange = 300
	case "avg15":
		utilrange = 900
	default:
		return nil, errors.New("Invalid fifth parameter.")
	}

	var typeflags uint
	switch utiltype {
	case "total", "":
		typeflags |= timeTotal
	case "user":
		typeflags |= timeUser
	case "system":
		typeflags |= timeSystem
	default:
		return nil, errors.New("Invalid third parameter.")
	}

	now := time.Now()
	query := procQuery{name: name, user: user, cmdline: cmdline}
	p.mutex.Lock()
	defer p.mutex.Unlock()
	if stats, ok := p.queries[query]; ok {
		stats.accessed = now
		if stats.err != nil {
			p.Debugf("CPU utilization gathering error %s", err)
			return nil, stats.err
		}
		if stats.tail == stats.head {
			return
		}
		totalnum := stats.tail - stats.head
		if totalnum < 0 {
			totalnum += maxHistory
		}
		if totalnum < 2 {
			return
		}
		if totalnum < utilrange {
			utilrange = totalnum
		}
		tail := &stats.history[stats.tail.dec()]
		head := &stats.history[stats.tail.sub(utilrange)]

		var ticks uint64
		if typeflags&timeUser != 0 {
			ticks += tail.utime - head.utime
		}
		if typeflags&timeSystem != 0 {
			ticks += tail.stime - head.stime
		}
		/* 1e9 (nanoseconds) * 1e2 (percent) * 1e1 (one digit decimal place) */
		ticks *= 1e12
		ticks /= uint64(tail.timestamp.Sub(head.timestamp))

		log.Tracef("Calling C function \"sysconf()\"")
		return math.Round(float64(ticks)/float64(C.sysconf(C._SC_CLK_TCK))) / 10, nil
	}
	stats := &cpuUtilStats{accessed: now, history: make([]cpuUtilData, maxHistory)}
	if cmdline != "" {
		stats.cmdlinePattern, err = regexp.Compile(cmdline)
	}
	if err == nil {
		p.queries[query] = stats
		p.Debugf("registered new CPU utilization query: %s, %s, %s", name, user, cmdline)
	} else {
		p.Debugf("cannot register CPU utilization query: %s", err)
	}
	return
}
func (p *PluginExport) prepareQuery(q *procQuery) (query *cpuUtilQuery, flags int, err error) {
	regxp, err := regexp.Compile(q.cmdline)
	if err != nil {
		return nil, 0, fmt.Errorf("cannot compile regex for %s: %s", q.cmdline, err.Error())
	}

	if query, err = newCpuUtilQuery(q, regxp); err != nil {
		return nil, 0, fmt.Errorf("cannot create CPU utilization query %+v: %s", q, err.Error())
	}

	if q.name != "" {
		flags |= procInfoName | procInfoCmdline
	}
	if q.user != "" {
		flags |= procInfoUser
	}
	if q.cmdline != "" {
		flags |= procInfoCmdline
	}
	if q.state != "" {
		flags |= procInfoState
	}

	return
}

// Export -
func (p *PluginExport) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "proc.mem":
		return p.exportProcMem(params)
	case "proc.num":
		return p.exportProcNum(params)
	}

	return nil, plugin.UnsupportedMetricError
}

func (p *PluginExport) exportProcMem(params []string) (result interface{}, err error) {
	var name, mode, cmdline, memtype string
	var usr *user.User

	switch len(params) {
	case 5:
		memtype = params[4]
		fallthrough
	case 4:
		cmdline = params[3]
		fallthrough
	case 3:
		mode = params[2]
		fallthrough
	case 2:
		if username := params[1]; username != "" {
			usr, err = user.Lookup(username)
			if err == user.UnknownUserError(username) {
				p.Debugf("Failed to obtain user '%s': %s", username, err.Error())
				return 0, nil
			}
			if err != nil {
				return nil, fmt.Errorf("Failed to obtain user '%s': %s", username, err.Error())
			}
		}
		fallthrough
	case 1:
		name = params[0]
	case 0:
	default:
		return nil, errors.New("Too many parameters.")
	}

	switch mode {
	case "sum", "", "avg", "max", "min":
	default:
		return nil, errors.New("Invalid third parameter.")
	}

	var mem uint64
	if memtype == "pmem" {
		mem, err = procfs.GetMemory("MemTotal")
		if err != nil {
			p.Debugf("cannot obtain memory: %s", err.Error())
			return 0, nil
		}

		if mem == 0 {
			return nil, errors.New("Total memory reported is 0.")
		}
	}

	var typeStr string
	if memtype != "size" && memtype != "pmem" {
		typeStr, err = getTypeString(memtype)
		if err != nil {
			return nil, err
		}
	}

	var count int
	var memSize, value float64
	var cmdRgx *regexp.Regexp
	if cmdline != "" {
		cmdRgx, err = regexp.Compile(cmdline)
		if err != nil {
			p.Debugf("Failed to compile provided regex expression '%s': %s", cmdline, err.Error())
			return 0, nil
		}
	}

	userID := int64(-1)
	if usr != nil {
		userID, err = strconv.ParseInt(usr.Uid, 10, 64)
		if err != nil {
			p.Logger.Tracef(
				"failed to convert user id '%s' to uint64 for user '%s'", usr.Uid, usr.Username)
			return 0, nil
		}
	}

	processes, err := getProcesses(procInfoName | procInfoCmdline | procInfoUser)
	if err != nil {
		return nil, fmt.Errorf("Failed to obtain processes: %s", err.Error())
	}

	for _, proc := range processes {
		if !p.validFile(proc, name, userID, cmdRgx) {
			continue
		}

		data, err := procfs.ReadAll("/proc/" + strconv.FormatInt(proc.pid, 10) + "/status")
		if err != nil {
			return nil, fmt.Errorf("Failed to read status file for pid '%d': %s", proc.pid, err.Error())
		}

		switch memtype {
		case "pmem":
			vmRSS, found, err := procfs.ByteFromProcFileData(data, "VmRSS")
			if err != nil {
				return nil, fmt.Errorf("Cannot obtain amount of VmRSS: %s", err.Error())
			}

			if !found {
				continue
			}

			value = float64(vmRSS) / float64(mem) * 100.00
		case "size":
			vmData, found, err := procfs.ByteFromProcFileData(data, "VmData")
			if err != nil {
				return nil, fmt.Errorf("Cannot obtain amount of VmData: %s", err.Error())
			}

			if !found {
				continue
			}

			vmStk, found, err := procfs.ByteFromProcFileData(data, "VmStk")
			if err != nil {
				return nil, fmt.Errorf("Cannot obtain amount of VmStk: %s", err.Error())
			}

			if !found {
				continue
			}

			vmExe, found, err := procfs.ByteFromProcFileData(data, "VmExe")
			if err != nil {
				return nil, fmt.Errorf("Cannot obtain amount of VmExe: %s", err.Error())
			}

			if !found {
				continue
			}
			value = float64(vmData + vmStk + vmExe)
		default:
			typeValue, found, err := procfs.ByteFromProcFileData(data, typeStr)
			if err != nil {
				return nil, fmt.Errorf("Cannot obtain amount of %s: %s", typeStr, err.Error())
			}

			if !found {
				continue
			}

			value = float64(typeValue)
		}

		if count != 0 {
			switch mode {
			case "max":
				memSize = getMax(memSize, value)
			case "min":
				memSize = getMin(memSize, value)
			default:
				memSize += value
			}
		} else {
			memSize = value
		}
		count++
	}

	if count == 0 {
		p.Debugf("no memory found for '%s'.", typeStr)
		return 0, nil
	}

	if mode == "avg" {
		return memSize / float64(count), nil
	}

	if memtype != "pmem" {
		return uint64(memSize), nil
	}
	return memSize, nil
}

func (p *PluginExport) exportProcNum(params []string) (interface{}, error) {
	var name, userName, state, cmdline string
	switch len(params) {
	case 4:
		cmdline = params[3]
		fallthrough
	case 3:
		switch params[2] {
		case "all", "":
		case "disk":
			state = "D"
		case "run":
			state = "R"
		case "sleep":
			state = "S"
		case "trace":
			state = "T"
		case "zomb":
			state = "Z"
		default:
			return nil, errors.New("Invalid third parameter.")
		}
		fallthrough
	case 2:
		userName = params[1]
		fallthrough
	case 1:
		name = params[0]
	case 0:
	default:
		return nil, errors.New("Too many parameters.")
	}

	var count int

	query, flags, err := p.prepareQuery(&procQuery{name, userName, cmdline, state})
	if err != nil {
		p.Debugf("Failed to prepare query: %s", err.Error())
		return count, nil
	}

	procs, err := getProcesses(flags)
	if err != nil {
		return nil, fmt.Errorf("Failed to get local processes: %s", err.Error())
	}

	for _, proc := range procs {
		if !query.match(proc) {
			continue
		}

		if state != proc.state && state != "" {
			continue
		}

		count++
	}

	return count, nil
}

func getMax(a, b float64) float64 {
	if a > b {
		return a
	}
	return b
}

func getMin(a, b float64) float64 {
	if a < b {
		return a
	}
	return b
}

func checkProcessName(cmdName, stats, name string) bool {
	return name == "" || stats == name || cmdName == name
}

func checkUserInfo(uid, puid int64) bool {
	return uid == -1 || uid == puid
}

func checkProccom(cmd string, cmdRgx *regexp.Regexp) bool {
	if cmdRgx == nil {
		return true
	}

	return cmdRgx.MatchString(cmd)
}

func getTypeString(t string) (string, error) {
	switch t {
	case "vsize", "":
		return "VmSize", nil
	case "rss":
		return "VmRSS", nil
	case "peak":
		return "VmPeak", nil
	case "swap":
		return "VmSwap", nil
	case "lib":
		return "VmLib", nil
	case "lck":
		return "VmLck", nil
	case "pin":
		return "VmPin", nil
	case "hwm":
		return "VmHWM", nil
	case "data":
		return "VmData", nil
	case "stk":
		return "VmStk", nil
	case "exe":
		return "VmExe", nil
	case "pte":
		return "VmPTE", nil
	default:
		return "", errors.New("Invalid fifth parameter.")
	}
}

func (p *PluginExport) validFile(proc *procInfo, name string, uid int64, cmdRgx *regexp.Regexp) bool {
	if proc == nil {
		return false
	}

	if !checkProcessName(proc.arg0, proc.name, name) {
		return false
	}

	if !checkUserInfo(uid, proc.userid) {
		return false
	}

	if !checkProccom(proc.cmdline, cmdRgx) {
		return false
	}

	return true
}

func init() {
	plugin.RegisterMetrics(&impl, "Proc", "proc.cpu.util", "Process CPU utilization percentage.")
	plugin.RegisterMetrics(&implExport, "ProcExporter",
		"proc.mem", "Process memory utilization values.",
		"proc.num", "The number of processes.",
	)
}
