/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package log

import (
	"container/heap"
	"strconv"
	"strings"
	"time"
	"zabbix/internal/agent"
	"zabbix/internal/plugin"
	"zabbix/pkg/zbxlib"
)

// Plugin -

const (
	defaultcapacity = 1
)

// Plugin -
type Plugin struct {
	plugin.Base
	queue logHeap
	// log monitoring tasks, mapped as clientid->itemid->task
	tasks        map[uint64]map[uint64]*logTask
	capacity     int
	runningTasks int
	newTasks     chan *logTask
	input        chan interface{}
}

type clientRequest struct {
	clientid uint64
	requests []*plugin.Request
	sink     plugin.ResultWriter
}

func (p *Plugin) processQueue(now time.Time) {
	seconds := now.Unix()
	for task := p.queue.Peek(); task != nil; task = p.queue.Peek() {
		if p.capacity == p.runningTasks {
			return
		}
		if task.scheduled.Unix() > seconds {
			return
		}
		heap.Pop(&p.queue)
		if task.active {
			p.runningTasks++
			// TODO: update task with latest configuration data

			// pass item key as parameter to allow task updates without worrying about synchronization
			go task.perform(task.key, p, now)
		}
	}
}

// updateTasks creates/update log monitoring tasks for the corresponding client.
func (p *Plugin) updateTasks(request *clientRequest, now time.Time) {
	var tasks map[uint64]*logTask
	var ok bool
	if tasks, ok = p.tasks[request.clientid]; !ok {
		tasks = make(map[uint64]*logTask)
		p.tasks[request.clientid] = tasks
	}

	for _, r := range request.requests {
		data, err := zbxlib.NewActiveMetric(r.Key, r.LastLogsize, r.Mtime)
		if err != nil {
			request.sink.Write(&plugin.Result{Itemid: r.Itemid, Ts: time.Now(), Error: err})
			continue
		}

		var task *logTask
		if task, ok = tasks[r.Itemid]; !ok {
			task = &logTask{
				clientid: request.clientid,
				output:   request.sink,
				active:   true,
				itemid:   r.Itemid,
				key:      r.Key,
				delay:    r.Delay,
				data:     data,
			}
			task.reschedule(now)
			tasks[r.Itemid] = task
			heap.Push(&p.queue, task)
		} else {
			task.key = r.Key
			if task.delay != r.Delay {
				task.delay = r.Delay
				p.queue.Update(task)
			}
		}
		task.updated = now
	}

	// remove tasks for items not monitored anymore
	for _, t := range tasks {
		if t.updated.Before(now) {
			t.deactivate()
			delete(tasks, t.itemid)
		}
	}
	if len(tasks) == 0 {
		delete(p.tasks, request.clientid)
		p.Debugf("removing client %d", request.clientid)
	}
}

func (p *Plugin) run() {
	p.Debugf("started log monitoring")
	ticker := time.NewTicker(time.Second)
run:
	for {
		select {
		case <-ticker.C:
			p.processQueue(time.Now())
		case v := <-p.input:
			if v == nil {
				break run
			}
			switch v.(type) {
			case *logTask:
				now := time.Now()
				p.runningTasks--
				task := v.(*logTask)
				if task.active {
					task.reschedule(now)
					heap.Push(&p.queue, task)
				}
				p.processQueue(now)
			case *clientRequest:
				now := time.Now()
				p.updateTasks(v.(*clientRequest), now)
				p.processQueue(now)
			}
		}
	}

	close(p.newTasks)
	close(p.input)
	p.Debugf("stopped log monitoring")
}

// plugin interfaces

func (p *Plugin) Start() {
	p.newTasks = make(chan *logTask, 100)
	p.input = make(chan interface{}, 100)

	go p.run()
}

func (p *Plugin) Stop() {
	p.input <- nil
}

func (p *Plugin) Watch(clientid uint64, requests []*plugin.Request, sink plugin.ResultWriter) {
	p.input <- &clientRequest{clientid: clientid, requests: requests, sink: sink}
}

func (p *Plugin) Configure(options map[string]string) {
	if val, ok := options["Capacity"]; ok {
		var err error
		if p.capacity, err = strconv.Atoi(val); err != nil {
			p.Warningf("invalid configuration parameter Plugins.%s.Workers value '%s', using default %d",
				strings.Title(p.Name()), val, defaultcapacity)
		} else {
			p.Debugf("setting maximum capacity to %d", p.capacity)
		}
	}
	// TODO: either access gobal configuration or move MaxLinesPerSecond to plugin configuration
	// TODO: MaxLinesPerSecond will be accessed from C code in multiple goroutines -
	// some update synchronzation would be preferable.
	zbxlib.SetMaxLinesPerSecond(agent.Options.MaxLinesPerSecond)
}

var impl Plugin

func init() {
	impl.capacity = defaultcapacity
	impl.tasks = make(map[uint64]map[uint64]*logTask)

	plugin.RegisterMetric(&impl, "log", "log", "Log file monitoring.")
	plugin.RegisterMetric(&impl, "log", "logrt", "Log file monitoring with log rotation support.")
	plugin.RegisterMetric(&impl, "log", "log.count", "Count of matched lines in log file monitoring.")
	plugin.RegisterMetric(&impl, "log", "logrt.count",
		"Count of matched lines in log file monitoring with log rotation support.")
}
