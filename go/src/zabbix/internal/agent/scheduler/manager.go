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
** GNU General Public License for more detailm.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

package scheduler

import (
	"container/heap"
	"errors"
	"time"
	"zabbix/internal/monitor"
	"zabbix/internal/plugin"
	"zabbix/pkg/itemutil"
	"zabbix/pkg/log"
)

type Manager struct {
	input   chan interface{}
	plugins map[string]*pluginAgent
	queue   pluginHeap
	owners  map[plugin.ResultWriter]*batch
}

type updateRequest struct {
	sink     plugin.ResultWriter
	requests []*plugin.Request
}

type Scheduler interface {
	UpdateTasks(writer plugin.ResultWriter, requests []*plugin.Request)
	FinishTask(task performer)
}

func (m *Manager) processUpdateRequest(update *updateRequest) {
	log.Debugf("processing update request (%d requests)", len(update.requests))

	// TODO: owner expiry - remove unused owners after tiemout (day+?)
	var owner *batch
	var ok bool
	if owner, ok = m.owners[update.sink]; !ok {
		owner = newBatch()
		m.owners[update.sink] = owner
	}

	now := time.Now()
	for _, r := range update.requests {
		var key string
		var err error
		var p *pluginAgent
		if key, _, err = itemutil.ParseKey(r.Key); err == nil {
			if p, ok = m.plugins[key]; !ok {
				err = errors.New("unknown metric")
			}
		}
		if err != nil {
			update.sink.Write(&plugin.Result{Itemid: r.Itemid, Error: err, Ts: now})
			log.Warningf("cannot monitor metric \"%s\": %s", r.Key, err.Error())
			continue
		}
		if err = owner.addRequest(p, r, update.sink, now); err != nil {
			update.sink.Write(&plugin.Result{Itemid: r.Itemid, Error: err, Ts: now})
			continue
		}

		if !p.queued() {
			heap.Push(&m.queue, p)
		} else {
			m.queue.Update(p)
		}
	}

	released := owner.cleanup(m.plugins, now)
	for _, p := range released {
		if p.refcount != 0 {
			continue
		}
		p.tasks = p.tasks[:0]
		if _, ok := p.impl.(plugin.Runner); ok {
			log.Debugf("start stopper task for plugin %s", p.impl.Name())
			task := &stopperTask{
				taskBase: taskBase{
					plugin:    p,
					scheduled: now.Add(priorityStopperTaskNs),
					active:    true,
				}}
			p.enqueueTask(task)

			if !p.queued() {
				heap.Push(&m.queue, p)
			} else {
				m.queue.Update(p)
			}
		} else {
			m.queue.Remove(p)
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
	task.finish()
	p := task.getPlugin()
	p.releaseCapacity(task)
	if p.active() && task.isActive() && task.reschedule() {
		p.enqueueTask(task)
	}
	if !p.queued() && p.hasCapacity() {
		heap.Push(&m.queue, p)
	}
}

func (m *Manager) run() {
	defer log.PanicHook()
	log.Debugf("starting Manager")
	ticker := time.NewTicker(time.Second)
run:
	for {
		select {
		case <-ticker.C:
			m.processQueue(time.Now())
		case v := <-m.input:
			if v == nil {
				break run
			}
			switch v.(type) {
			case *updateRequest:
				m.processUpdateRequest(v.(*updateRequest))
				m.processQueue(time.Now())
			case performer:
				m.processFinishRequest(v.(performer))
				m.processQueue(time.Now())
			}
		}
	}
	close(m.input)
	log.Debugf("Manager has been stopped")
	monitor.Unregister()
}

func (m *Manager) init() {
	m.input = make(chan interface{}, 10)
	m.queue = make(pluginHeap, 0, len(plugin.Metrics))
	m.owners = make(map[plugin.ResultWriter]*batch)

	m.plugins = make(map[string]*pluginAgent)
	for key, acc := range plugin.Metrics {
		m.plugins[key] = &pluginAgent{
			impl:         acc,
			tasks:        make(performerHeap, 0),
			capacity:     10,
			usedCapacity: 0,
			index:        -1,
			refcount:     0,
		}
	}
}

func (m *Manager) Start() {
	m.init()
	monitor.Register()
	go m.run()
}

func (m *Manager) Stop() {
	m.input <- nil
}

func (m *Manager) UpdateTasks(writer plugin.ResultWriter, requests []*plugin.Request) {
	r := updateRequest{sink: writer, requests: requests}
	m.input <- &r
}

func (m *Manager) FinishTask(task performer) {
	m.input <- task
}
