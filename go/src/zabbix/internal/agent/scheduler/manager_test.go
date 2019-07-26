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
	"reflect"
	"strconv"
	"testing"
	"time"
	"zabbix/internal/plugin"
	"zabbix/pkg/itemutil"
	"zabbix/pkg/log"
)

// getNextCheck calculates simplified nextcheck based on the specified delay string and current time
func getNextcheck(delay string, from time.Time) (nextcheck time.Time) {
	simple_delay, _ := strconv.ParseInt(delay, 10, 64)
	from_seconds := from.Unix()
	return time.Unix(from_seconds-from_seconds%simple_delay+simple_delay, 0)
}

type callTracker interface {
	call(key string)
	called() map[string][]time.Time
}

type mockPlugin struct {
	calls map[string][]time.Time
	now   *time.Time
}

func (p *mockPlugin) call(key string) {
	if p.calls == nil {
		p.calls = make(map[string][]time.Time)
	}
	if p.calls[key] == nil {
		p.calls[key] = make([]time.Time, 0, 20)
	}
	p.calls[key] = append(p.calls[key], *p.now)
}

func (p *mockPlugin) called() map[string][]time.Time {
	return p.calls
}

type mockExporterPlugin struct {
	plugin.Base
	mockPlugin
}

func (p *mockExporterPlugin) Export(key string, params []string) (result interface{}, err error) {
	p.call(key)
	return
}

type mockCollectorPlugin struct {
	plugin.Base
	mockPlugin
	period int
}

func (p *mockCollectorPlugin) Collect() (err error) {
	p.call("$collect")
	return
}

func (p *mockCollectorPlugin) Period() (period int) {
	return p.period
}

type mockCollectorExporterPlugin struct {
	plugin.Base
	mockPlugin
	period int
}

func (p *mockCollectorExporterPlugin) Export(key string, params []string) (result interface{}, err error) {
	p.call(key)
	return
}

func (p *mockCollectorExporterPlugin) Collect() (err error) {
	p.call("$collect")
	return
}

func (p *mockCollectorExporterPlugin) Period() (period int) {
	return p.period
}

type mockRunnerPlugin struct {
	plugin.Base
	mockPlugin
}

func (p *mockRunnerPlugin) Start() {
	p.call("$start")
}

func (p *mockRunnerPlugin) Stop() {
	p.call("$stop")
}

type watchTracker interface {
	watched() []*plugin.Request
}

type mockWatcherPlugin struct {
	plugin.Base
	mockPlugin
	requests []*plugin.Request
}

func (p *mockWatcherPlugin) Watch(requests []*plugin.Request, sink plugin.ResultWriter) {
	p.call("$watch")
	p.requests = requests
}

func (p *mockWatcherPlugin) watched() []*plugin.Request {
	return p.requests
}

type mockRunnerWatcherPlugin struct {
	plugin.Base
	mockPlugin
	requests []*plugin.Request
}

func (p *mockRunnerWatcherPlugin) Start() {
	p.call("$start")
}

func (p *mockRunnerWatcherPlugin) Stop() {
	p.call("$stop")
}

func (p *mockRunnerWatcherPlugin) Watch(requests []*plugin.Request, sink plugin.ResultWriter) {
	p.call("$watch")
	p.requests = requests
}

func (p *mockRunnerWatcherPlugin) watched() []*plugin.Request {
	return p.requests
}

type resultCacheMock struct {
	results []*plugin.Result
}

func (c *resultCacheMock) Write(r *plugin.Result) {
	c.results = append(c.results, r)
}

type mockManager struct {
	Manager
	sink      chan Performer
	now       time.Time
	startTime time.Time
}

func (m *mockManager) finishTasks() {
	for {
		select {
		case p := <-m.sink:
			m.processFinishRequest(p)
		default:
			return
		}
	}
}

func (m *mockManager) iterate(t *testing.T, iters int) {
	for i := 0; i < iters; i++ {
		m.now = m.now.Add(time.Second)
		m.processQueue(m.now)
		m.finishTasks()
	}
}

func (m *mockManager) mockInit(t *testing.T) {
	m.init()
	clock := time.Now().Unix()
	m.startTime = time.Unix(clock-clock%10, 0)
	t.Logf("starting time %s", m.startTime.Format(time.Stamp))
	m.now = m.startTime
}

func (m *mockManager) mockTasks() {
	for _, p := range m.plugins {
		tasks := p.tasks
		p.tasks = make(performerHeap, 0, len(tasks))
		for j, performer := range tasks {
			switch performer.(type) {
			case *CollectorTask:
				collector := p.impl.(plugin.Collector)
				task := &mockCollectorTask{
					Task: Task{
						plugin:    performer.Plugin(),
						scheduled: getNextcheck(fmt.Sprintf("%d", collector.Period()), m.now).Add(priorityCollectorTaskNs),
						index:     -1,
						active:    performer.Active(),
					},
					sink: m.sink,
				}
				p.enqueueTask(task)
			case *ExporterTask:
				e := tasks[j].(*ExporterTask)
				task := &mockExporterTask{
					Task: Task{
						plugin:    performer.Plugin(),
						scheduled: getNextcheck(e.item.delay, m.now).Add(priorityExporterTaskNs),
						index:     -1,
						active:    performer.Active(),
					},
					sink: m.sink,
					item: e.item,
				}
				e.item.task = task
				p.enqueueTask(task)
			case *StarterTask:
				task := &mockStarterTask{
					Task: Task{
						plugin:    performer.Plugin(),
						scheduled: m.now,
						index:     -1,
						active:    performer.Active(),
					},
					sink: m.sink,
				}
				p.enqueueTask(task)
			case *StopperTask:
				task := &mockStopperTask{
					Task: Task{
						plugin:    performer.Plugin(),
						scheduled: m.now.Add(priorityStopperTaskNs),
						index:     -1,
						active:    performer.Active(),
					},
					sink: m.sink,
				}
				p.enqueueTask(task)
			case *WatcherTask:
				w := tasks[j].(*WatcherTask)
				task := &mockWatcherTask{
					Task: Task{
						plugin:    performer.Plugin(),
						scheduled: m.now.Add(priorityWatcherTaskNs),
						index:     -1,
						active:    performer.Active(),
					},
					sink:       m.sink,
					resultSink: w.sink,
					requests:   w.requests,
				}
				p.enqueueTask(task)
			default:
				p.enqueueTask(performer)
			}
			tasks[j].SetIndex(-1)
		}
		m.queue.Update(p)
	}
}

// checks if the times timestamps match the offsets within the specified range
func (m *mockManager) checkTimeline(t *testing.T, name string, times []time.Time, offsets []int, iters int) {
	start := m.now.Add(-time.Second * time.Duration(iters-1))
	to := int(m.now.Sub(m.startTime) / time.Second)
	from := to - iters + 1
	var left, right int

	// find the range start in timestamps
	if len(times) != 0 {
		for times[left].Before(start) {
			left++
			if left == len(times) {
				break
			}
		}
	}

	// find the range start in offsetse
	if len(offsets) != 0 {
		for offsets[right] < from {
			right++
			if right == len(offsets) {
				break
			}
		}
	}

	for left < len(times) && right < len(offsets) {
		if times[left].After(m.now) {
			if offsets[right] <= to {
				t.Errorf("plugin %s: no matching timestamp for offset %d", name, offsets[right])
			}
			return
		}
		if offsets[right] > to {
			t.Errorf("plugin %s: no matching offset for timestamp %s", name, times[left].Format(time.Stamp))
			return
		}

		offsetTime := m.startTime.Add(time.Second * time.Duration(offsets[right]))
		if !offsetTime.Equal(times[left]) {
			t.Errorf("plugin %s: offset %d time %s does not match timestamp %s", name, offsets[right],
				offsetTime.Format(time.Stamp), times[left].Format(time.Stamp))
			return
		}
		left++
		right++
	}
	if left != len(times) && !times[left].After(m.now) {
		t.Errorf("plugin %s: no matching offset for timestamp %s", name, times[left].Format(time.Stamp))
		return
	}

	if right != len(offsets) && offsets[right] <= to {
		t.Errorf("plugin %s: no matching timestamp for offset %d", name, offsets[right])
		return
	}
}

// checks plugin call timeline within the specified range
func (m *mockManager) checkPluginTimeline(t *testing.T, plugins []plugin.Accessor, calls []map[string][]int, iters int) {
	for i, p := range plugins {
		tracker := p.(callTracker).called()
		for key, offsets := range calls[i] {
			m.checkTimeline(t, p.Name()+":"+key, tracker[key], offsets, iters)
		}
	}
}

type mockExporterTask struct {
	Task
	item *Item
	sink chan Performer
}

func (t *mockExporterTask) Perform(s Scheduler) {
	key, params, _ := itemutil.ParseKey(t.item.key)
	_, _ = t.plugin.impl.(plugin.Exporter).Export(key, params)
	t.sink <- t
}

func (t *mockExporterTask) Reschedule() bool {
	t.scheduled = getNextcheck(t.item.delay, t.scheduled)
	return true
}

type mockCollectorTask struct {
	Task
	sink chan Performer
}

func (t *mockCollectorTask) Perform(s Scheduler) {
	_ = t.plugin.impl.(plugin.Collector).Collect()
	t.sink <- t
}

func (t *mockCollectorTask) Reschedule() bool {
	t.scheduled = getNextcheck(fmt.Sprintf("%d", t.plugin.impl.(plugin.Collector).Period()), t.scheduled)
	return true
}

func (t *mockCollectorTask) Weight() int {
	return t.plugin.capacity
}

type mockStarterTask struct {
	Task
	sink chan Performer
}

func (t *mockStarterTask) Perform(s Scheduler) {
	t.plugin.impl.(plugin.Runner).Start()
	t.sink <- t
}

func (t *mockStarterTask) Reschedule() bool {
	return false
}

func (t *mockStarterTask) Weight() int {
	return t.plugin.capacity
}

type mockStopperTask struct {
	Task
	sink chan Performer
}

func (t *mockStopperTask) Perform(s Scheduler) {
	t.plugin.impl.(plugin.Runner).Stop()
	t.sink <- t
}

func (t *mockStopperTask) Reschedule() bool {
	return false
}

func (t *mockStopperTask) Weight() int {
	return t.plugin.capacity
}

type mockWatcherTask struct {
	Task
	sink       chan Performer
	resultSink plugin.ResultWriter
	requests   []*plugin.Request
}

func (t *mockWatcherTask) Perform(s Scheduler) {
	t.plugin.impl.(plugin.Watcher).Watch(t.requests, t.resultSink)
	t.sink <- t
}

func (t *mockWatcherTask) Reschedule() bool {
	return false
}

func (t *mockWatcherTask) Weight() int {
	return t.plugin.capacity
}

func checkExporterTasks(t *testing.T, m *Manager, sink plugin.ResultWriter, items []*Item) {
	lastCheck := time.Time{}
	n := 0
	for p := m.queue.Peek(); p != nil; p = m.queue.Peek() {
		if performer := p.peekTask(); performer != nil {
			if performer.Scheduled().Before(lastCheck) {
				t.Errorf("out of order tasks detected")
			}
			heap.Pop(&m.queue)
			p.popTask()
			n++
			if p.peekTask() != nil {
				heap.Push(&m.queue, p)
			}
		}
	}
	if len(items) != n {
		t.Errorf("Expected %d tasks while got %d", len(items), n)
	}

	var owner *Owner
	var ok bool
	if owner, ok = m.owners[sink]; !ok {
		t.Errorf("Cannot find owner of the specified result writer sink")
		return
	}

	for _, item := range items {
		if it, ok := owner.items[item.itemid]; ok {
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

	if len(items) != len(owner.items) {
		t.Errorf("Expected %d queued items while got %d", len(items), len(owner.items))
	}
}

func TestTaskCreate(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	plugin.ClearRegistry()
	plugins := make([]mockExporterPlugin, 3)
	for i := range plugins {
		p := &plugins[i]
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetric(p, name, name, "")
	}

	var manager Manager
	manager.init()

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

	var cache resultCacheMock
	update := UpdateRequest{
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{Itemid: item.itemid, Key: item.key, Delay: item.delay})
	}

	manager.processUpdateRequest(&update)

	if len(manager.queue) != 3 {
		t.Errorf("Expected %d plugins queued while got %d", 3, len(manager.queue))
	}

	checkExporterTasks(t, &manager, &cache, items)
}

func TestTaskUpdate(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	plugin.ClearRegistry()
	plugins := make([]mockExporterPlugin, 3)
	for i := range plugins {
		p := &plugins[i]
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetric(p, name, name, "")
	}

	var manager Manager
	manager.init()

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

	var cache resultCacheMock
	update := UpdateRequest{
		sink:     &cache,
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

	checkExporterTasks(t, &manager, &cache, items)
}

func TestTaskUpdateInvalidInterval(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	plugin.ClearRegistry()
	plugins := make([]mockExporterPlugin, 3)
	for i := range plugins {
		p := &plugins[i]
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetric(p, name, name, "")
	}

	var manager Manager
	manager.init()

	items := []*Item{
		&Item{itemid: 1, delay: "151", key: "debug1"},
		&Item{itemid: 2, delay: "103", key: "debug2"},
	}

	var cache resultCacheMock
	update := UpdateRequest{
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{Itemid: item.itemid, Key: item.key, Delay: item.delay})
	}
	manager.processUpdateRequest(&update)

	items[0].delay = "xyz"
	update.requests = update.requests[:0]
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{Itemid: item.itemid, Key: item.key, Delay: item.delay})
	}
	manager.processUpdateRequest(&update)

	if len(manager.queue) != 1 {
		t.Errorf("Expected %d plugins queued while got %d", 1, len(manager.queue))
	}
}

func TestTaskDelete(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	plugin.ClearRegistry()
	plugins := make([]mockExporterPlugin, 3)
	for i := range plugins {
		p := &plugins[i]
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetric(p, name, name, "")
	}

	var manager Manager
	manager.init()

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

	var cache resultCacheMock
	update := UpdateRequest{
		sink:     &cache,
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

	checkExporterTasks(t, &manager, &cache, items)
}

func TestSchedule(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	manager := mockManager{sink: make(chan Performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 3)
	for i := range plugins {
		plugins[i] = &mockExporterPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}}
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetric(plugins[i], name, name, "")
	}
	manager.mockInit(t)

	items := []*Item{
		&Item{itemid: 1, delay: "1", key: "debug1"},
		&Item{itemid: 2, delay: "2", key: "debug2"},
		&Item{itemid: 3, delay: "5", key: "debug3"},
	}

	calls := []map[string][]int{
		map[string][]int{"debug1": []int{1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20}},
		map[string][]int{"debug2": []int{2, 4, 6, 8, 10, 12, 14, 16, 18, 20}},
		map[string][]int{"debug3": []int{5, 10, 15, 20}},
	}

	var cache resultCacheMock
	update := UpdateRequest{
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{Itemid: item.itemid, Key: item.key, Delay: item.delay})
	}
	manager.processUpdateRequest(&update)
	manager.mockTasks()

	manager.iterate(t, 20)
	manager.checkPluginTimeline(t, plugins, calls, 20)
}

func TestScheduleCapacity(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	manager := mockManager{sink: make(chan Performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 2)
	for i := range plugins {
		plugins[i] = &mockExporterPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}}
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetric(plugins[i], name, name, "")
	}
	manager.mockInit(t)

	p := manager.plugins["debug2"]
	p.capacity = 2

	items := []*Item{
		&Item{itemid: 1, delay: "1", key: "debug1"},
		&Item{itemid: 2, delay: "2", key: "debug2"},
		&Item{itemid: 3, delay: "2", key: "debug2"},
		&Item{itemid: 4, delay: "2", key: "debug2"},
	}

	calls := []map[string][]int{
		map[string][]int{"debug1": []int{1, 2, 3, 4, 5, 6, 7, 8, 9, 10}},
		map[string][]int{"debug2": []int{2, 2, 3, 4, 4, 5, 6, 6, 7, 8, 8, 9, 10, 10}},
	}

	var cache resultCacheMock
	update := UpdateRequest{
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{Itemid: item.itemid, Key: item.key, Delay: item.delay})
	}
	manager.processUpdateRequest(&update)
	manager.mockTasks()

	manager.iterate(t, 10)
	manager.checkPluginTimeline(t, plugins, calls, 10)
}

func TestScheduleUpdate(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	manager := mockManager{sink: make(chan Performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 3)
	for i := range plugins {
		plugins[i] = &mockExporterPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}}
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetric(plugins[i], name, name, "")
	}
	manager.mockInit(t)

	items := []*Item{
		&Item{itemid: 1, delay: "1", key: "debug1"},
		&Item{itemid: 2, delay: "1", key: "debug2"},
		&Item{itemid: 3, delay: "1", key: "debug3"},
	}

	calls := []map[string][]int{
		map[string][]int{"debug1": []int{1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 16, 17, 18, 19, 20}},
		map[string][]int{"debug2": []int{1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 16, 17, 18, 19, 20}},
		map[string][]int{"debug3": []int{1, 2, 3, 4, 5, 16, 17, 18, 19, 20}},
	}

	var cache resultCacheMock
	update := UpdateRequest{
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{Itemid: item.itemid, Key: item.key, Delay: item.delay})
	}
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[:2]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[:0]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[:3]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)
}

func TestCollectorSchedule(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	manager := mockManager{sink: make(chan Performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 1)
	for i := range plugins {
		plugins[i] = &mockCollectorPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}, period: 2}
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetric(plugins[i], name, name, "")
	}
	manager.mockInit(t)

	items := []*Item{
		&Item{itemid: 1, delay: "1", key: "debug1"},
	}

	calls := []map[string][]int{
		map[string][]int{"$collect": []int{2, 4, 6, 8, 10, 12, 14, 16, 18, 20}},
	}

	var cache resultCacheMock
	update := UpdateRequest{
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{Itemid: item.itemid, Key: item.key, Delay: item.delay})
	}
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 20)
	manager.checkPluginTimeline(t, plugins, calls, 20)
}

func TestCollectorScheduleUpdate(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	manager := mockManager{sink: make(chan Performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 3)
	for i := range plugins {
		plugins[i] = &mockCollectorPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}, period: 2}
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetric(plugins[i], name, name, "")
	}
	manager.mockInit(t)

	items := []*Item{
		&Item{itemid: 1, delay: "5", key: "debug1"},
		&Item{itemid: 2, delay: "5", key: "debug2"},
		&Item{itemid: 3, delay: "5", key: "debug3"},
	}

	calls := []map[string][]int{
		map[string][]int{"$collect": []int{2, 4, 6, 8, 10, 12, 14}},
		map[string][]int{"$collect": []int{2, 4, 6, 8, 10, 22, 24}},
		map[string][]int{"$collect": []int{2, 4, 22, 24}},
	}

	var cache resultCacheMock
	update := UpdateRequest{
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{Itemid: item.itemid, Key: item.key, Delay: item.delay})
	}
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[:2]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[:1]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[:0]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[1:3]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)
}

func TestRunner(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	manager := mockManager{sink: make(chan Performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 3)
	for i := range plugins {
		plugins[i] = &mockRunnerPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}}
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetric(plugins[i], name, name, "")
	}
	log.Debugf("plugins %+v", plugin.Metrics)
	manager.mockInit(t)

	items := []*Item{
		&Item{itemid: 1, delay: "5", key: "debug1"},
		&Item{itemid: 2, delay: "5", key: "debug2"},
		&Item{itemid: 3, delay: "5", key: "debug3"},
	}

	calls := []map[string][]int{
		map[string][]int{"$start": []int{1, 5}, "$stop": []int{4, 6}},
		map[string][]int{"$start": []int{1, 5, 7}, "$stop": []int{3, 6, 8}},
		map[string][]int{"$start": []int{1, 5, 8}, "$stop": []int{2, 6}},
	}

	var cache resultCacheMock
	update := UpdateRequest{
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{Itemid: item.itemid, Key: item.key, Delay: item.delay})
	}
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	update.requests = update.requests[:2]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	update.requests = update.requests[:1]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	update.requests = update.requests[:0]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	update.requests = update.requests[:3]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	update.requests = update.requests[:0]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	update.requests = update.requests[1:2]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	update.requests = update.requests[1:2]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

}

func checkWatchRequests(t *testing.T, p plugin.Accessor, requests []*plugin.Request) {
	tracker := p.(watchTracker)
	if !reflect.DeepEqual(tracker.watched(), requests) {
		expected := ""
		for _, r := range requests {
			expected += fmt.Sprintf("%+v,", *r)
		}
		returned := ""
		for _, r := range tracker.watched() {
			returned += fmt.Sprintf("%+v,", *r)
		}
		t.Errorf("expected watch requests %s while got %s", expected, returned)
	}
}

func TestWatcher(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	manager := mockManager{sink: make(chan Performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 3)
	for i := range plugins {
		plugins[i] = &mockWatcherPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}}
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetric(plugins[i], name, name, "")
	}
	log.Debugf("plugins %+v", plugin.Metrics)
	manager.mockInit(t)

	items := []*Item{
		&Item{itemid: 1, delay: "5", key: "debug1"},
		&Item{itemid: 2, delay: "5", key: "debug2[1]"},
		&Item{itemid: 3, delay: "5", key: "debug2[2]"},
		&Item{itemid: 4, delay: "5", key: "debug3[1]"},
		&Item{itemid: 5, delay: "5", key: "debug3[2]"},
		&Item{itemid: 6, delay: "5", key: "debug3[3]"},
	}

	calls := []map[string][]int{
		map[string][]int{"$watch": []int{1, 2, 3, 4, 5}},
		map[string][]int{"$watch": []int{1, 2, 3, 4, 5}},
		map[string][]int{"$watch": []int{1, 2, 5}},
	}

	var cache resultCacheMock
	update := UpdateRequest{
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{Itemid: item.itemid, Key: item.key, Delay: item.delay})
	}
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	checkWatchRequests(t, plugins[0], update.requests[0:1])
	checkWatchRequests(t, plugins[1], update.requests[1:3])
	checkWatchRequests(t, plugins[2], update.requests[3:6])

	update.requests = update.requests[:5]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	checkWatchRequests(t, plugins[0], update.requests[0:1])
	checkWatchRequests(t, plugins[1], update.requests[1:3])
	checkWatchRequests(t, plugins[2], update.requests[3:5])

	update.requests = update.requests[:3]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	checkWatchRequests(t, plugins[0], update.requests[0:1])
	checkWatchRequests(t, plugins[1], update.requests[1:3])

	update.requests = update.requests[:2]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	checkWatchRequests(t, plugins[0], update.requests[0:1])
	checkWatchRequests(t, plugins[1], update.requests[1:2])

	update.requests = update.requests[:6]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	checkWatchRequests(t, plugins[0], update.requests[0:1])
	checkWatchRequests(t, plugins[1], update.requests[1:3])
	checkWatchRequests(t, plugins[2], update.requests[3:6])
}

func TestCollectorExporterSchedule(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	manager := mockManager{sink: make(chan Performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 1)
	for i := range plugins {
		plugins[i] = &mockCollectorExporterPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}, period: 2}
		plugin.RegisterMetric(plugins[i], "debug", "debug", "")
	}
	manager.mockInit(t)

	items := []*Item{
		&Item{itemid: 1, delay: "2", key: "debug[1]"},
		&Item{itemid: 2, delay: "2", key: "debug[2]"},
		&Item{itemid: 3, delay: "2", key: "debug[3]"},
	}

	calls := []map[string][]int{
		map[string][]int{"debug": []int{3, 3, 3, 5, 5, 5, 7, 7, 7, 9, 9, 9}, "$collect": []int{2, 4, 6, 8, 10}},
	}

	var cache resultCacheMock
	update := UpdateRequest{
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{Itemid: item.itemid, Key: item.key, Delay: item.delay})
	}
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 10)

	manager.checkPluginTimeline(t, plugins, calls, 10)
}

func TestRunnerWatcher(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	manager := mockManager{sink: make(chan Performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 3)
	for i := range plugins {
		plugins[i] = &mockRunnerWatcherPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}}
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetric(plugins[i], name, name, "")
	}
	log.Debugf("plugins %+v", plugin.Metrics)
	manager.mockInit(t)

	items := []*Item{
		&Item{itemid: 1, delay: "5", key: "debug1"},
		&Item{itemid: 2, delay: "5", key: "debug2[1]"},
		&Item{itemid: 3, delay: "5", key: "debug2[2]"},
		&Item{itemid: 4, delay: "5", key: "debug3[1]"},
		&Item{itemid: 5, delay: "5", key: "debug3[2]"},
		&Item{itemid: 6, delay: "5", key: "debug3[3]"},
	}

	calls := []map[string][]int{
		map[string][]int{"$watch": []int{2, 6, 11}, "$start": []int{1}, "$stop": []int{16}},
		map[string][]int{"$watch": []int{2, 6, 22}, "$start": []int{1, 21}, "$stop": []int{11, 26}},
		map[string][]int{"$watch": []int{2, 27}, "$start": []int{1, 26}, "$stop": []int{6}},
	}

	var cache resultCacheMock
	update := UpdateRequest{
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{Itemid: item.itemid, Key: item.key, Delay: item.delay})
	}
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	checkWatchRequests(t, plugins[0], update.requests[0:1])
	checkWatchRequests(t, plugins[1], update.requests[1:3])
	checkWatchRequests(t, plugins[2], update.requests[3:6])

	update.requests = update.requests[:3]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	checkWatchRequests(t, plugins[0], update.requests[0:1])
	checkWatchRequests(t, plugins[1], update.requests[1:3])

	update.requests = update.requests[:1]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	checkWatchRequests(t, plugins[0], update.requests[0:1])

	update.requests = update.requests[:0]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[1:3]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	checkWatchRequests(t, plugins[1], update.requests[:2])

	update.requests = update.requests[2:5]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	checkWatchRequests(t, plugins[2], update.requests[0:3])
}

func TestMultiCollectorExporterSchedule(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	manager := mockManager{sink: make(chan Performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 1)
	for i := range plugins {
		plugins[i] = &mockCollectorExporterPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}, period: 2}
		plugin.RegisterMetric(plugins[i], "debug", "debug", "")
	}
	manager.mockInit(t)

	items := []*Item{
		&Item{itemid: 1, delay: "2", key: "debug[1]"},
	}

	calls := []map[string][]int{
		map[string][]int{"debug": []int{3, 3, 5, 5, 7, 9}, "$collect": []int{2, 4, 6, 8, 10}},
	}

	var cache1, cache2 resultCacheMock
	update := UpdateRequest{
		sink:     &cache1,
		requests: make([]*plugin.Request, 0),
	}

	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{Itemid: item.itemid, Key: item.key, Delay: item.delay})
	}
	manager.processUpdateRequest(&update)
	update.sink = &cache2
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[:0]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.sink = &cache1
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)
}

func TestMultiRunnerWatcher(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	manager := mockManager{sink: make(chan Performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 1)
	for i := range plugins {
		plugins[i] = &mockRunnerWatcherPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}}
		plugin.RegisterMetric(plugins[i], "debug", "debug", "")
	}
	log.Debugf("plugins %+v", plugin.Metrics)
	manager.mockInit(t)

	items := []*Item{
		&Item{itemid: 1, delay: "5", key: "debug[1]"},
		&Item{itemid: 2, delay: "5", key: "debug[2]"},
		&Item{itemid: 3, delay: "5", key: "debug[3]"},
	}

	calls := []map[string][]int{
		map[string][]int{"$watch": []int{2, 3, 6, 17, 21}, "$start": []int{1, 16}, "$stop": []int{11}},
	}

	var cache1, cache2 resultCacheMock
	update := UpdateRequest{
		sink:     &cache1,
		requests: make([]*plugin.Request, 0),
	}

	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{Itemid: item.itemid, Key: item.key, Delay: item.delay})
	}
	manager.processUpdateRequest(&update)
	update.sink = &cache2
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.sink = &cache1
	manager.processUpdateRequest(&update)
	update.sink = &cache2
	update.requests = update.requests[:0]
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.sink = &cache1
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[:1]
	update.sink = &cache2
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.sink = &cache1
	manager.processUpdateRequest(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

}
