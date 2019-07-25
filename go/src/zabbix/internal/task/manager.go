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

package task

import (
	"container/heap"
	"errors"
	"time"
	"zabbix/internal/monitor"
	"zabbix/internal/plugin"
	"zabbix/pkg/itemutil"
	"zabbix/pkg/log"
)

type Item struct {
	itemid      uint64
	delay       string
	unsupported bool
	key         string
	task        *ExporterTask
}

type Manager struct {
	input   chan interface{}
	items   map[uint64]*Item
	plugins map[string]*Plugin
	queue   pluginHeap
}

type UpdateRequest struct {
	writer   plugin.ResultWriter
	requests []*plugin.Request
}

type Scheduler interface {
	UpdateTasks(writer plugin.ResultWriter, requests []*plugin.Request)
	FinishTask(performer Performer)
}

func (m *Manager) processUpdateRequest(update *UpdateRequest) {
	log.Debugf("processing update request from instance %p (%d requests)", update.writer, len(update.requests))

	now := time.Now()
	for _, r := range update.requests {
		var key string
		var err error
		var p *Plugin
		if key, _, err = itemutil.ParseKey(r.Key); err == nil {
			var ok bool
			if p, ok = m.plugins[key]; !ok {
				err = errors.New("unknown metric")
			}
		}
		if err != nil {
			update.writer.Write(&plugin.Result{Itemid: r.Itemid, Error: err, Ts: now})
			log.Warningf("cannot monitor metric \"%s\": %s", r.Key, err.Error())
			continue
		}

		switch p.impl.(type) {
		case plugin.Collector:
			if !p.active {
				log.Debugf("Start collector task for plugin %s", p.impl.Name())
				/*
					task := &CollectorTask{
						Task: Task{
							plugin:    plugin,
							created:   m.updateTime,
							scheduled: GetItemNextcheck(item, m.updateTime)}}
					plugin.Enqueue(task)
				*/
			}
		case plugin.Exporter:
			var nextcheck time.Time
			if nextcheck, err = itemutil.GetNextcheck(r.Itemid, r.Delay, false, now); err != nil {
				update.writer.Write(&plugin.Result{Itemid: r.Itemid, Error: err, Ts: now})
				// TODO: remove task from queue and if necessary plugin from parent queue
				continue
			}

			if item, ok := m.items[r.Itemid]; !ok {
				item = &Item{itemid: r.Itemid,
					delay: r.Delay,
					key:   r.Key,
				}
				m.items[r.Itemid] = item

				item.task = &ExporterTask{
					Task: Task{
						plugin:    p,
						scheduled: nextcheck,
					},
					writer: update.writer,
					item:   item,
				}
				log.Debugf("Enqueue task %+v", *item.task)
				p.Enqueue(item.task)

				if !p.active {
					heap.Push(&m.queue, p)
					p.active = true
				} else {
					m.queue.Update(p)
				}
			} else {
				if item.delay != r.Delay && !item.unsupported {
					log.Debugf("number of sheduled tasks: %d", len(p.tasks))
					if len(p.tasks) > 0 {
						log.Debugf("item.task: %p, queued task: %p", item.task, p.tasks[0])
					}
					p.tasks.Update(item.task)
					m.queue.Update(p)
					item.task.scheduled = nextcheck
				}
				item.delay = r.Delay
				item.key = r.Key
			}
		}
	}
}

func (m *Manager) processQueue() {
	ts := time.Now()
	for p := m.queue.Peek(); p != nil; p = m.queue.Peek() {
		if performer := p.PeekQueue(); performer != nil {
			if performer.Scheduled().After(ts) {
				break
			}
			heap.Pop(&m.queue)
			if !p.BeginTask(m) {
				continue
			}
			if p.PeekQueue() != nil {
				heap.Push(&m.queue, p)
			}
		} else {
			// plugins with empty task queue should not be in Manager queue
			heap.Pop(&m.queue)
		}
	}
}

func (m *Manager) run() {
	log.Debugf("starting Manager")
	ticker := time.NewTicker(time.Second)
run:
	for {
		select {
		case <-ticker.C:
			m.processQueue()
		case v := <-m.input:
			if v == nil {
				break run
			}
			switch v.(type) {
			case *UpdateRequest:
				m.processUpdateRequest(v.(*UpdateRequest))
			case Performer:
				performer := v.(Performer)
				performer.Reschedule()
				p := performer.Plugin()
				if p.EndTask(performer) {
					heap.Push(&m.queue, p)
				}
				m.processQueue()
			}
		}
	}
	close(m.input)
	log.Debugf("Manager has been stopped")
	monitor.Unregister()
}

func (m *Manager) Start() {
	m.items = make(map[uint64]*Item)
	m.input = make(chan interface{}, 10)
	m.queue = make(pluginHeap, 0, len(plugin.Metrics))

	m.plugins = make(map[string]*Plugin)
	for key, acc := range plugin.Metrics {
		m.plugins[key] = &Plugin{
			impl:         acc,
			tasks:        make(performerHeap, 0),
			active:       false,
			capacity:     10,
			usedCapacity: 0,
			index:        -1,
		}
	}

	monitor.Register()
	go m.run()
}

func (m *Manager) Stop() {
	m.input <- nil
}

func (m *Manager) UpdateTasks(writer plugin.ResultWriter, requests []*plugin.Request) {
	r := UpdateRequest{writer: writer, requests: requests}
	m.input <- &r
}

func (m *Manager) FinishTask(p Performer) {
	m.input <- p
}
