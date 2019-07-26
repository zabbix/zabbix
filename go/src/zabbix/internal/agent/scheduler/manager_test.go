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
	"testing"
	"time"
	"zabbix/internal/plugin"
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
