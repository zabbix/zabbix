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

package scheduler

import (
	"container/heap"
	"errors"
	"math"
	"sort"
	"strconv"
	"strings"
	"time"
	"zabbix/internal/agent"
	"zabbix/internal/monitor"
	"zabbix/internal/plugin"
	"zabbix/pkg/itemutil"
	"zabbix/pkg/log"
)

type Manager struct {
	input   chan interface{}
	plugins map[string]*pluginAgent
	queue   pluginHeap
	clients map[uint64]*client
}

type updateRequest struct {
	clientID           uint64
	sink               plugin.ResultWriter
	requests           []*plugin.Request
	refreshUnsupported int
}

type Scheduler interface {
	UpdateTasks(clientID uint64, writer plugin.ResultWriter, refreshUnsupported int, requests []*plugin.Request)
	FinishTask(task performer)
}

func (m *Manager) processUpdateRequest(update *updateRequest, now time.Time) {
	log.Debugf("processing update request (%d requests)", len(update.requests))

	// TODO: client expiry - remove unused owners after timeout (day+?)
	var requestClient *client
	var ok bool
	if requestClient, ok = m.clients[update.clientID]; !ok {
		requestClient = newClient(update.clientID, update.refreshUnsupported)
		m.clients[update.clientID] = requestClient
	}

	for _, r := range update.requests {
		var key string
		var err error
		var p *pluginAgent
		if key, _, err = itemutil.ParseKey(r.Key); err == nil {
			if p, ok = m.plugins[key]; !ok {
				err = errors.New("Unknown metric")
			}
		}
		if err == nil {
			err = requestClient.addRequest(p, r, update.sink, now)
		}
		if err != nil {
			if task, ok := requestClient.exporters[r.Itemid]; ok {
				log.Debugf("deactivate task")
				task.deactivate()
			}
			update.sink.Write(&plugin.Result{Itemid: r.Itemid, Error: err, Ts: now})
			log.Warningf("cannot monitor metric \"%s\": %s", r.Key, err.Error())
			continue
		}

		if !p.queued() {
			heap.Push(&m.queue, p)
		} else {
			m.queue.Update(p)
		}
	}

	released := requestClient.cleanup(m.plugins, now)
	for _, p := range released {
		if p.refcount != 0 {
			continue
		}
		log.Debugf("deactivate unused plugin %s", p.name())

		// deactivate recurring tasks
		for deactivate := true; deactivate; {
			deactivate = false
			for _, t := range p.tasks {
				if t.isRecurring() {
					t.deactivate()
					// deactivation can change tasks ordering, so repeat the iteraton if task was deactivated
					deactivate = true
					break
				}
			}
		}

		// queue stopper task if plugin has Runner interface
		if _, ok := p.impl.(plugin.Runner); ok {
			task := &stopperTask{
				taskBase: taskBase{plugin: p, active: true},
			}
			if err := task.reschedule(now); err != nil {
				log.Debugf("cannot schedule stopper task for plugin %s", p.name())
				continue
			}
			p.enqueueTask(task)
			log.Debugf("created stopper task for plugin %s", p.name())

			if p.queued() {
				m.queue.Update(p)
			}
		}

		// queue plugin if there are still some tasks left to be finished before deactivating
		if len(p.tasks) != 0 {
			if !p.queued() {
				heap.Push(&m.queue, p)
			}
		}
	}
}

func (m *Manager) processQueue(now time.Time) {
	seconds := now.Unix()
	for p := m.queue.Peek(); p != nil; p = m.queue.Peek() {
		if task := p.peekTask(); task != nil {
			if task.getScheduled().Unix() > seconds {
				break
			}
			heap.Pop(&m.queue)
			if p.hasCapacity() {
				p.popTask()
				p.reserveCapacity(task)
				task.perform(m)
				if p.hasCapacity() {
					heap.Push(&m.queue, p)
				}
			}
		} else {
			// plugins with empty task queue should not be in Manager queue
			heap.Pop(&m.queue)
		}
	}
}

func (m *Manager) processFinishRequest(task performer) {
	p := task.getPlugin()
	p.releaseCapacity(task)
	if p.active() && task.isActive() && task.isRecurring() {
		if err := task.reschedule(time.Now()); err != nil {
			log.Warningf("cannot reschedule plugin %s: %s", p.impl.Name(), err)
		} else {
			p.enqueueTask(task)
		}
	}
	if !p.queued() && p.hasCapacity() {
		heap.Push(&m.queue, p)
	}
}

// rescheduleQueue reschedules all queued tasks. This is done whenever time
// difference between ticks exceeds limits (for example during daylight saving changes).
func (m *Manager) rescheduleQueue(now time.Time) {
	// easier to rebuild queues than update each element
	queue := make(pluginHeap, 0, len(m.queue))
	for _, p := range m.queue {
		tasks := p.tasks
		p.tasks = make(performerHeap, 0, len(tasks))
		for _, t := range tasks {
			if err := t.reschedule(now); err == nil {
				p.enqueueTask(t)
			}
		}
		heap.Push(&queue, p)
	}
	m.queue = queue
}

func (m *Manager) run() {
	defer log.PanicHook()
	log.Debugf("starting manager")
	// Adjust ticker creation at the 0 nanosecond timestamp. In reality it will have at least
	// some microseconds, which will be enough to include all scheduled tasks at this second
	// even with nanosecond priority adjustment.
	lastTick := time.Now()
	time.Sleep(time.Duration(1e9 - lastTick.Nanosecond()))
	ticker := time.NewTicker(time.Second)
run:
	for {
		select {
		case <-ticker.C:
			now := time.Now()
			diff := now.Sub(lastTick)
			interval := time.Second * 10
			if diff <= -interval || diff >= interval {
				log.Warningf("detected %d time difference between queue checks, rescheduling tasks",
					int(math.Abs(float64(diff))/1e9))
				m.rescheduleQueue(now)
			}
			lastTick = now
			m.processQueue(now)
		case v := <-m.input:
			if v == nil {
				break run
			}
			switch v.(type) {
			case *updateRequest:
				m.processUpdateRequest(v.(*updateRequest), time.Now())
				m.processQueue(time.Now())
			case performer:
				m.processFinishRequest(v.(performer))
				m.processQueue(time.Now())
			}
		}
	}
	close(m.input)
	log.Debugf("manager has been stopped")
	monitor.Unregister()
}

func (m *Manager) init() {
	m.input = make(chan interface{}, 10)
	m.queue = make(pluginHeap, 0, len(plugin.Metrics))
	m.clients = make(map[uint64]*client)

	metrics := make([]plugin.Accessor, 0, len(plugin.Metrics))
	m.plugins = make(map[string]*pluginAgent)
	for key, acc := range plugin.Metrics {
		capacity := plugin.DefaultCapacity
		section := strings.Title(acc.Name())
		if options, ok := agent.Options.Plugins[section]; ok {
			if cap, ok := options["Capacity"]; ok {
				var err error
				if capacity, err = strconv.Atoi(cap); err != nil {
					log.Warningf("invalid configuration parameter Plugins.%s.Capacity value '%s', using default %d",
						section, cap, plugin.DefaultCapacity)
				}
			}
		}
		m.plugins[key] = &pluginAgent{
			impl:         acc,
			tasks:        make(performerHeap, 0),
			capacity:     capacity,
			usedCapacity: 0,
			index:        -1,
			refcount:     0,
		}
		metrics = append(metrics, acc)
	}

	// log available plugins
	sort.Slice(metrics, func(i, j int) bool {
		return metrics[i].Name() < metrics[j].Name()
	})
	lastPlugin := ""
	for _, acc := range metrics {
		if acc.Name() != lastPlugin {
			interfaces := ""
			if _, ok := acc.(plugin.Exporter); ok {
				interfaces += "exporter, "
			}
			if _, ok := acc.(plugin.Collector); ok {
				interfaces += "collector, "
			}
			if _, ok := acc.(plugin.Runner); ok {
				interfaces += "runner, "
			}
			if _, ok := acc.(plugin.Watcher); ok {
				interfaces += "watcher, "
			}
			if _, ok := acc.(plugin.Configurator); ok {
				interfaces += "configurator, "
			}
			interfaces = interfaces[:len(interfaces)-2]
			log.Infof("using plugin '%s' providing following interfaces: %s", acc.Name(), interfaces)
			lastPlugin = acc.Name()
		}
	}
}

func (m *Manager) Start() {
	monitor.Register()
	go m.run()
}

func (m *Manager) Stop() {
	m.input <- nil
}

func (m *Manager) UpdateTasks(clientID uint64, writer plugin.ResultWriter, refreshUnsupported int, requests []*plugin.Request) {
	r := updateRequest{clientID: clientID, sink: writer, requests: requests, refreshUnsupported: refreshUnsupported}
	m.input <- &r
}

func (m *Manager) FinishTask(task performer) {
	m.input <- task
}

func NewManager() *Manager {
	var m Manager
	m.init()
	return &m
}
