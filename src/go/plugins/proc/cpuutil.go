// +build linux

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

package proc

/*
#include <unistd.h>
*/
import "C"

import (
	"errors"
	"math"
	"os/user"
	"regexp"
	"strconv"
	"sync"
	"time"

	"zabbix.com/pkg/log"
	"zabbix.com/pkg/plugin"
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

var impl Plugin = Plugin{
	stats:   make(map[int64]*cpuUtil),
	queries: make(map[procQuery]*cpuUtilStats),
}

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
			p.Debugf("removed unused CPU utilisation query %+v", q)
			delete(p.queries, q)
			continue
		}
		var query *cpuUtilQuery
		if query, stats.err = newCpuUtilQuery(&q, stats.cmdlinePattern); stats.err != nil {
			p.Debugf("cannot create CPU utilisation query %+v: %s", q, stats.err)
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
	if processes, err = p.getProcesses(flags); err != nil {
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
			p.Debugf("cannot get process %d CPU utilisation statistics: %s", pid, stat.err)
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
			p.Debugf("CPU utilisation gathering error %s", err)
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

		return math.Round(float64(ticks)/float64(C.sysconf(C._SC_CLK_TCK))) / 10, nil
	}
	stats := &cpuUtilStats{accessed: now, history: make([]cpuUtilData, maxHistory)}
	if cmdline != "" {
		stats.cmdlinePattern, err = regexp.Compile(cmdline)
	}
	if err == nil {
		p.queries[query] = stats
		p.Debugf("registered new CPU utilisation query: %s, %s, %s", name, user, cmdline)
	} else {
		p.Debugf("cannot register CPU utilisation query: %s", err)
	}
	return
}

func init() {
	plugin.RegisterMetrics(&impl, "Proc", "proc.cpu.util", "Process CPU utilisation percentage.")
}
