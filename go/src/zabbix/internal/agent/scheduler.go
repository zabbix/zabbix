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

package agent

import (
	"container/heap"
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

type Scheduler struct {
	input chan interface{}
	items map[uint64]*Item
	queue plugin.Heap
}

type UpdateRequest struct {
	writer   plugin.ResultWriter
	requests []*plugin.Request
}

func (s *Scheduler) processUpdateRequest(update *UpdateRequest) {
	log.Debugf("processing update request from instance %p (%d requests)", update.writer, len(update.requests))

	now := time.Now()
	for _, r := range update.requests {
		var key string
		var err error
		var plg *plugin.Plugin
		if key, _, err = itemutil.ParseKey(r.Key); err == nil {
			plg, err = plugin.Get(key)
		}
		if err != nil {
			update.writer.Write(&plugin.Result{Itemid: r.Itemid, Error: err, Ts: now})
			log.Warningf("cannot monitor metric \"%s\": %s", r.Key, err.Error())
			continue
		}

		switch plg.Impl.(type) {
		case plugin.Collector:
			if !plg.Active {
				log.Debugf("Start collector task for plugin %s", plg.Impl.Name())
				/*
					task := &CollectorTask{
						Task: Task{
							plugin:    plugin,
							created:   s.updateTime,
							scheduled: GetItemNextcheck(item, s.updateTime)}}
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

			if item, ok := s.items[r.Itemid]; !ok {
				item = &Item{itemid: r.Itemid,
					delay: r.Delay,
					key:   r.Key,
				}
				s.items[r.Itemid] = item

				item.task = &ExporterTask{
					Task: Task{
						plugin:    plg,
						scheduled: nextcheck,
					},
					writer: update.writer,
					item:   *item,
				}
				log.Debugf("Enqueue task %+v", *item.task)
				plg.Enqueue(item.task)

				if !plg.Active {
					heap.Push(&s.queue, plg)
					plg.Active = true
				} else {
					s.queue.Update(plg)
				}
			} else {
				if item.delay != r.Delay && !item.unsupported {
					log.Debugf("number of sheduled tasks: %d", len(plg.Tasks))
					if len(plg.Tasks) > 0 {
						log.Debugf("item.task: %p, queued task: %p", item.task, plg.Tasks[0])
					}
					plg.Tasks.Update(item.task)
					s.queue.Update(plg)
					item.task.scheduled = nextcheck
				}
				item.delay = r.Delay
				item.key = r.Key
				item.task.item = *item
			}
		}
	}
}

func (s *Scheduler) processQueue() {
	ts := time.Now()
	for plg := s.queue.Peek(); plg != nil; plg = s.queue.Peek() {
		if performer := plg.PeekQueue(); performer != nil {
			if performer.Scheduled().After(ts) {
				break
			}
			heap.Pop(&s.queue)
			if !plg.BeginTask(s.input) {
				continue
			}
			if plg.PeekQueue() != nil {
				heap.Push(&s.queue, plg)
			}
		} else {
			// plugins with empty task queue should not be in scheduler queue
			heap.Pop(&s.queue)
		}
	}
}

func (s *Scheduler) run() {
	log.Debugf("starting scheduler")
	ticker := time.NewTicker(time.Second)
run:
	for {
		select {
		case <-ticker.C:
			s.processQueue()
		case v := <-s.input:
			if v == nil {
				break run
			}
			switch v.(type) {
			case *UpdateRequest:
				s.processUpdateRequest(v.(*UpdateRequest))
			case plugin.Performer:
				performer := v.(plugin.Performer)
				p := performer.Plugin()
				if p.EndTask(performer) {
					heap.Push(&s.queue, p)
				}
				s.processQueue()
			}
		}
	}
	close(s.input)
	log.Debugf("scheduler has been stopped")
	monitor.Unregister()
}

func (s *Scheduler) Start() {
	s.items = make(map[uint64]*Item)
	s.input = make(chan interface{}, 10)
	s.queue = make(plugin.Heap, 0, plugin.Count())
	monitor.Register()
	go s.run()
}

func (s *Scheduler) Stop() {
	s.input <- nil
}

func (s *Scheduler) Update(writer plugin.ResultWriter, requests []*plugin.Request) {
	r := UpdateRequest{writer: writer, requests: requests}
	s.input <- &r
}
