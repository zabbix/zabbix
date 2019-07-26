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
	"fmt"
	"strconv"
	"testing"
	"time"
	"zabbix/internal/plugin"
	"zabbix/pkg/itemutil"
	"zabbix/pkg/log"
)

type DebugPlugin struct {
	plugin.Base
	used int
}

func (p *DebugPlugin) Export(key string, params []string) (result interface{}, err error) {
	p.used++
	return
}

type ResultCacheMock struct {
	results []*plugin.Result
}

func (c *ResultCacheMock) Write(r *plugin.Result) {
	c.results = append(c.results, r)
}

func validateQueue(t *testing.T, m *Manager, items []*Item) {
	lastCheck := time.Time{}
	n := 0
	for p := m.queue.Peek(); p != nil; p = m.queue.Peek() {
		if performer := p.PeekQueue(); performer != nil {
			if performer.Scheduled().Before(lastCheck) {
				t.Errorf("out of order tasks detected")
			}
			heap.Pop(&m.queue)
			p.PopQueue()
			n++
			if p.PeekQueue() != nil {
				heap.Push(&m.queue, p)
			}
		}
	}
	if len(items) != n {
		t.Errorf("Expected %d tasks while got %d", len(items), n)
	}

	for _, item := range items {
		if it, ok := m.items[item.itemid]; ok {
			if it.delay != item.delay {
				t.Errorf("Expected item %d delay %s while got %s", item.itemid, item.delay, it.delay)
			}
			if it.key != item.key {
				t.Errorf("Expected item %d key %s while got %s", item.itemid, item.key, it.key)
			}
		} else {
			t.Errorf("Item %d was not queued", item.itemid)
		}
	}

	if len(items) != len(m.items) {
		t.Errorf("Expected %d queued items while got %d", len(items), len(m.items))
	}
}

func TestTaskCreate(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	plugin.ClearRegistry()
	plugins := make([]DebugPlugin, 3)
	for i, p := range plugins {
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetric(&p, name, name, "")
	}

	var manager Manager
	manager.init()

	var cache ResultCacheMock

	items := []*Item{
		&Item{itemid: 1, delay: "151", key: "debug1"},
		&Item{itemid: 2, delay: "103", key: "debug2"},
		&Item{itemid: 3, delay: "79", key: "debug3"},
		&Item{itemid: 4, delay: "17", key: "debug1"},
		&Item{itemid: 5, delay: "7", key: "debug2"},
		&Item{itemid: 6, delay: "1", key: "debug3"},
		&Item{itemid: 7, delay: "63", key: "debug1"},
		&Item{itemid: 8, delay: "47", key: "debug2"},
		&Item{itemid: 9, delay: "31", key: "debug3"},
	}

	update := UpdateRequest{
		writer:   &cache,
		requests: make([]*plugin.Request, 0),
	}

	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{Itemid: item.itemid, Key: item.key, Delay: item.delay})
	}

	manager.processUpdateRequest(&update)

	if len(manager.queue) != 3 {
		t.Errorf("Expected %d plugins queued while got %d", 3, len(manager.queue))
	}

	validateQueue(t, &manager, items)
}

func TestTaskUpdate(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	plugin.ClearRegistry()
	plugins := make([]DebugPlugin, 3)
	for i, p := range plugins {
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetric(&p, name, name, "")
	}

	var manager Manager
	manager.init()

	var cache ResultCacheMock

	items := []*Item{
		&Item{itemid: 1, delay: "151", key: "debug1"},
		&Item{itemid: 2, delay: "103", key: "debug2"},
		&Item{itemid: 3, delay: "79", key: "debug3"},
		&Item{itemid: 4, delay: "17", key: "debug1"},
		&Item{itemid: 5, delay: "7", key: "debug2"},
		&Item{itemid: 6, delay: "1", key: "debug3"},
		&Item{itemid: 7, delay: "63", key: "debug1"},
		&Item{itemid: 8, delay: "47", key: "debug2"},
		&Item{itemid: 9, delay: "31", key: "debug3"},
	}

	update := UpdateRequest{
		writer:   &cache,
		requests: make([]*plugin.Request, 0),
	}

	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{Itemid: item.itemid, Key: item.key, Delay: item.delay})
	}
	manager.processUpdateRequest(&update)

	for _, item := range items {
		item.delay = "10" + item.delay
		item.key = item.key + "[1]"
	}
	update.requests = update.requests[:0]
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{Itemid: item.itemid, Key: item.key, Delay: item.delay})
	}
	manager.processUpdateRequest(&update)

	if len(manager.queue) != 3 {
		t.Errorf("Expected %d plugins queued while got %d", 3, len(manager.queue))
	}

	validateQueue(t, &manager, items)
}

func TestTaskDelete(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	plugin.ClearRegistry()
	plugins := make([]DebugPlugin, 3)
	for i, p := range plugins {
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetric(&p, name, name, "")
	}

	var manager Manager
	manager.init()

	var cache ResultCacheMock

	items := []*Item{
		&Item{itemid: 1, delay: "151", key: "debug1"},
		&Item{itemid: 2, delay: "103", key: "debug2"},
		&Item{itemid: 3, delay: "79", key: "debug3"}, // remove
		&Item{itemid: 4, delay: "17", key: "debug1"},
		&Item{itemid: 5, delay: "7", key: "debug2"},
		&Item{itemid: 6, delay: "1", key: "debug3"}, // remove
		&Item{itemid: 7, delay: "63", key: "debug1"},
		&Item{itemid: 8, delay: "47", key: "debug2"}, // remove
		&Item{itemid: 9, delay: "31", key: "debug3"}, // remove
	}

	update := UpdateRequest{
		writer:   &cache,
		requests: make([]*plugin.Request, 0),
	}

	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{Itemid: item.itemid, Key: item.key, Delay: item.delay})
	}
	manager.processUpdateRequest(&update)

	items[2] = items[6]
	items = items[:cap(items)-4]
	update.requests = update.requests[:0]
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{Itemid: item.itemid, Key: item.key, Delay: item.delay})
	}
	manager.processUpdateRequest(&update)

	if len(manager.queue) != 2 {
		t.Errorf("Expected %d plugins queued while got %d", 2, len(manager.queue))
	}

	validateQueue(t, &manager, items)
}

func getNextcheck(delay string, from time.Time) (nextcheck time.Time) {
	simple_delay, _ := strconv.ParseInt(delay, 10, 64)
	from_seconds := from.Unix()
	return time.Unix(from_seconds-from_seconds%simple_delay+simple_delay, 0)
}

type MockTask struct {
	Task
	item    *Item
	manager *Manager
	sink    chan Performer
}

func (t *MockTask) Perform(s Scheduler) {
	key, params, _ := itemutil.ParseKey(t.item.key)
	_, _ = t.plugin.impl.(plugin.Exporter).Export(key, params)
	t.sink <- t
}

func (t *MockTask) Reschedule() {
	t.scheduled = getNextcheck(t.item.delay, t.scheduled)
}

func finishTasks(m *Manager, sink chan Performer) {
	for {
		select {
		case p := <-sink:
			m.processFinishRequest(p)
		default:
			return
		}
	}
}

func TestSchedule(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	plugin.ClearRegistry()
	plugins := make([]DebugPlugin, 3)
	for i := range plugins {
		p := &plugins[i]
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetric(p, name, name, "")
	}

	sink := make(chan Performer, 10)
	var manager Manager
	manager.init()

	items := []*Item{
		&Item{itemid: 1, delay: "1", key: "debug1"},
		&Item{itemid: 2, delay: "2", key: "debug2"},
		&Item{itemid: 3, delay: "5", key: "debug3"},
	}

	clock := time.Now().Unix()
	now := time.Unix(clock-clock%10, 0)

	// construct manager queue with mock tasks
	for _, item := range items {
		var key string
		var err error
		if key, _, err = itemutil.ParseKey(item.key); err != nil {
			t.Errorf("Unexpected itemutil.ParseKey failure: %s", err.Error())
		}
		var p *Plugin
		var ok bool
		if p, ok = manager.plugins[key]; !ok {
			t.Errorf("Cannot find plugin %s", key)
		}
		task := MockTask{
			Task: Task{
				plugin:    p,
				scheduled: getNextcheck(item.delay, now),
			},
			item:    item,
			manager: &manager,
			sink:    sink,
		}
		p.Enqueue(&task)
		if p.index == -1 {
			heap.Push(&manager.queue, p)
		}
	}

	uses := [][]int{
		[]int{1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20},
		[]int{0, 1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6, 7, 7, 8, 8, 9, 9, 10},
		[]int{0, 0, 0, 0, 1, 1, 1, 1, 1, 2, 2, 2, 2, 2, 3, 3, 3, 3, 3, 4},
	}

	for i := 0; i < 20; i++ {
		now = now.Add(time.Second)
		manager.processQueue(now)
		finishTasks(&manager, sink)

		for j := range plugins {
			p := &plugins[j]
			if uses[j][i] != p.used {
				t.Errorf("Wrong use count for plugin %s at step %d: expected %d while got %d",
					p.Name(), i+1, uses[j][i], p.used)
			}
		}
	}
}

func TestCapacity(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	plugin.ClearRegistry()
	plugins := make([]DebugPlugin, 2)
	for i := range plugins {
		p := &plugins[i]
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetric(p, name, name, "")
	}

	sink := make(chan Performer, 10)
	var manager Manager
	manager.init()

	p := manager.plugins["debug2"]
	p.capacity = 2

	items := []*Item{
		&Item{itemid: 1, delay: "1", key: "debug1"},
		&Item{itemid: 2, delay: "2", key: "debug2[1]"},
		&Item{itemid: 3, delay: "2", key: "debug2[2]"},
		&Item{itemid: 4, delay: "2", key: "debug2[3]"},
	}

	clock := time.Now().Unix()
	now := time.Unix(clock-clock%10, 0)

	// construct manager queue with mock tasks
	for _, item := range items {
		var key string
		var err error
		if key, _, err = itemutil.ParseKey(item.key); err != nil {
			t.Errorf("Unexpected itemutil.ParseKey failure: %s", err.Error())
		}
		var p *Plugin
		var ok bool
		if p, ok = manager.plugins[key]; !ok {
			t.Errorf("Cannot find plugin %s", key)
		}
		task := MockTask{
			Task: Task{
				plugin:    p,
				scheduled: getNextcheck(item.delay, now),
			},
			item:    item,
			manager: &manager,
			sink:    sink,
		}
		p.Enqueue(&task)
		if p.index == -1 {
			heap.Push(&manager.queue, p)
		}
	}

	uses := [][]int{
		[]int{1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20},
		[]int{0, 2, 3, 5, 6, 8, 9, 11, 12, 14, 15, 17, 18, 20, 21, 23, 24, 26, 27, 29},
	}

	for i := 0; i < 20; i++ {
		now = now.Add(time.Second)
		manager.processQueue(now)
		finishTasks(&manager, sink)

		for j := range plugins {
			p := &plugins[j]
			if uses[j][i] != p.used {
				t.Errorf("Wrong use count for plugin %s at step %d: expected %d while got %d",
					p.Name(), i, uses[j][i], p.used)
			}
		}
	}
}
