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

package scheduler

import (
	"container/heap"
	"fmt"
	"reflect"
	"strconv"
	"testing"
	"time"

	"git.zabbix.com/ap/plugin-support/conf"
	"git.zabbix.com/ap/plugin-support/log"
	"git.zabbix.com/ap/plugin-support/plugin"
	"zabbix.com/internal/agent"
	"zabbix.com/internal/agent/alias"
	"zabbix.com/pkg/itemutil"
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

func (p *mockExporterPlugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
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

func (p *mockCollectorExporterPlugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
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

type mockPassiveRunnerPlugin struct {
	plugin.Base
	mockPlugin
}

func (p *mockPassiveRunnerPlugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	return
}
func (p *mockPassiveRunnerPlugin) Start() {
	p.call("$start")
}

func (p *mockPassiveRunnerPlugin) Stop() {
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

func (p *mockWatcherPlugin) Watch(requests []*plugin.Request, ctx plugin.ContextProvider) {
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

func (p *mockRunnerWatcherPlugin) Watch(requests []*plugin.Request, ctx plugin.ContextProvider) {
	p.call("$watch")
	p.requests = requests
}

func (p *mockRunnerWatcherPlugin) watched() []*plugin.Request {
	return p.requests
}

type mockConfiguratorPlugin struct {
	plugin.Base
	mockPlugin
	options interface{}
}

func (p *mockConfiguratorPlugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	p.call("$configure")
}

func (p *mockConfiguratorPlugin) Validate(options interface{}) (err error) {
	return
}

type resultCacheMock struct {
	results []*plugin.Result
}

func (c *resultCacheMock) Write(r *plugin.Result) {
	c.results = append(c.results, r)
}

func (c *resultCacheMock) Flush() {
}

func (pc *resultCacheMock) SlotsAvailable() int {
	return 1
}

func (pc *resultCacheMock) PersistSlotsAvailable() int {
	return 1
}

type mockManager struct {
	Manager
	sink      chan performer
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
	m.aliases, _ = alias.NewManager(nil)
	clock := time.Now().Unix()
	m.startTime = time.Unix(clock-clock%10, 100)
	t.Logf("starting time %s", m.startTime.Format(time.Stamp))
	m.now = m.startTime
}

func (m *mockManager) update(update *updateRequest) {
	m.processUpdateRequest(update, m.now)
}

func (m *mockManager) mockTasks() {
	index := make(map[exporterTaskAccessor]uint64)
	for clientid, client := range m.clients {
		for _, task := range client.exporters {
			index[task] = clientid
		}
		client.exporters = make(map[uint64]exporterTaskAccessor)
	}
	for _, p := range m.plugins {
		tasks := p.tasks
		p.tasks = make(performerHeap, 0, len(tasks))
		for j, task := range tasks {
			switch t := task.(type) {
			case *collectorTask:
				collector := p.impl.(plugin.Collector)
				mockTask := &mockCollectorTask{
					taskBase: taskBase{
						plugin:    task.getPlugin(),
						scheduled: getNextcheck(fmt.Sprintf("%d", collector.Period()), m.now).Add(priorityCollectorTaskNs),
						index:     -1,
						active:    task.isActive(),
						recurring: true,
					},
					sink: m.sink,
				}
				p.enqueueTask(mockTask)
			case *exporterTask:
				mockTask := &mockExporterTask{
					exporterTask: exporterTask{
						taskBase: taskBase{
							plugin:    task.getPlugin(),
							scheduled: getNextcheck(t.item.delay, m.now).Add(priorityExporterTaskNs),
							index:     -1,
							active:    task.isActive(),
							recurring: true,
						},
						item:   t.item,
						client: t.client,
						meta:   t.meta,
					},
					sink: m.sink,
				}
				p.enqueueTask(mockTask)
				m.clients[index[t]].exporters[t.item.itemid] = mockTask
			case *directExporterTask:
				mockTask := &mockExporterTask{
					exporterTask: exporterTask{
						taskBase: taskBase{
							plugin:    task.getPlugin(),
							scheduled: getNextcheck(t.item.delay, m.now).Add(priorityExporterTaskNs),
							index:     -1,
							active:    task.isActive(),
							recurring: true,
						},
						item:   t.item,
						client: t.client,
						meta:   t.meta,
					},
					sink: m.sink,
				}
				p.enqueueTask(mockTask)
			case *starterTask:
				mockTask := &mockStarterTask{
					taskBase: taskBase{
						plugin:    task.getPlugin(),
						scheduled: m.now,
						index:     -1,
						active:    task.isActive(),
					},
					sink: m.sink,
				}
				p.enqueueTask(mockTask)
			case *stopperTask:
				mockTask := &mockStopperTask{
					taskBase: taskBase{
						plugin:    task.getPlugin(),
						scheduled: m.now.Add(priorityStopperTaskNs),
						index:     -1,
						active:    task.isActive(),
					},
					sink: m.sink,
				}
				p.enqueueTask(mockTask)
			case *watcherTask:
				mockTask := &mockWatcherTask{
					taskBase: taskBase{
						plugin:    task.getPlugin(),
						scheduled: m.now.Add(priorityWatcherTaskNs),
						index:     -1,
						active:    task.isActive(),
					},
					sink:     m.sink,
					requests: t.requests,
					client:   t.client,
				}
				p.enqueueTask(mockTask)
			case *configuratorTask:
				mockTask := &mockConfigerTask{
					taskBase: taskBase{
						plugin:    task.getPlugin(),
						scheduled: m.now.Add(priorityWatcherTaskNs),
						index:     -1,
						active:    task.isActive(),
					},
					options: t.options,
					sink:    m.sink,
				}
				p.enqueueTask(mockTask)
			default:
				p.enqueueTask(task)
			}
			tasks[j].setIndex(-1)
		}
		m.pluginQueue.Update(p)
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

	// find the range start in offsets
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
				t.Errorf("Plugin %s: no matching timestamp for offset %d", name, offsets[right])
			}
			return
		}
		if offsets[right] > to {
			t.Errorf("Plugin %s: no matching offset for timestamp %s", name, times[left].Format(time.Stamp))
			return
		}

		offsetTime := m.startTime.Add(time.Second * time.Duration(offsets[right]))
		if !offsetTime.Equal(times[left]) {
			t.Errorf("Plugin %s: offset %d time %s does not match timestamp %s", name, offsets[right],
				offsetTime.Format(time.Stamp), times[left].Format(time.Stamp))
			return
		}
		left++
		right++
	}
	if left != len(times) && !times[left].After(m.now) {
		t.Errorf("Plugin %s: no matching offset for timestamp %s", name, times[left].Format(time.Stamp))
		return
	}

	if right != len(offsets) && offsets[right] <= to {
		t.Errorf("Plugin %s: no matching timestamp for offset %d", name, offsets[right])
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
	exporterTask
	sink chan performer
}

func (t *mockExporterTask) perform(s Scheduler) {
	key, params, _ := itemutil.ParseKey(t.item.key)
	_, _ = t.plugin.impl.(plugin.Exporter).Export(key, params, t)
	t.sink <- t
}

func (t *mockExporterTask) reschedule(now time.Time) (err error) {
	t.scheduled = getNextcheck(t.item.delay, t.scheduled)
	return
}

func (t *mockExporterTask) task() (task *exporterTask) {
	return &t.exporterTask
}

// plugin.ContextProvider interface

func (t *mockExporterTask) Output() (output plugin.ResultWriter) {
	return nil
}

func (t *mockExporterTask) Meta() (meta *plugin.Meta) {
	return &t.meta
}

func (t *mockExporterTask) GlobalRegexp() plugin.RegexpMatcher {
	return t.client.GlobalRegexp()
}

type mockCollectorTask struct {
	taskBase
	sink chan performer
}

func (t *mockCollectorTask) perform(s Scheduler) {
	_ = t.plugin.impl.(plugin.Collector).Collect()
	t.sink <- t
}

func (t *mockCollectorTask) reschedule(now time.Time) (err error) {
	t.scheduled = getNextcheck(fmt.Sprintf("%d", t.plugin.impl.(plugin.Collector).Period()), t.scheduled)
	return
}

func (t *mockCollectorTask) getWeight() int {
	return t.plugin.maxCapacity
}

type mockStarterTask struct {
	taskBase
	sink chan performer
}

func (t *mockStarterTask) perform(s Scheduler) {
	t.plugin.impl.(plugin.Runner).Start()
	t.sink <- t
}

func (t *mockStarterTask) reschedule(now time.Time) (err error) {
	return
}

func (t *mockStarterTask) getWeight() int {
	return t.plugin.maxCapacity
}

type mockStopperTask struct {
	taskBase
	sink chan performer
}

func (t *mockStopperTask) perform(s Scheduler) {
	t.plugin.impl.(plugin.Runner).Stop()
	t.sink <- t
}

func (t *mockStopperTask) reschedule(now time.Time) (err error) {
	return
}

func (t *mockStopperTask) getWeight() int {
	return t.plugin.maxCapacity
}

type mockWatcherTask struct {
	taskBase
	sink       chan performer
	resultSink plugin.ResultWriter
	requests   []*plugin.Request
	client     ClientAccessor
}

func (t *mockWatcherTask) perform(s Scheduler) {
	log.Debugf("%s %v", t.plugin.impl.Name(), t.requests)
	t.plugin.impl.(plugin.Watcher).Watch(t.requests, t)
	t.sink <- t
}

func (t *mockWatcherTask) reschedule(now time.Time) (err error) {
	return
}

func (t *mockWatcherTask) getWeight() int {
	return t.plugin.maxCapacity
}

// plugin.ContextProvider interface

func (t *mockWatcherTask) ClientID() (clientid uint64) {
	return t.client.ID()
}

func (t *mockWatcherTask) ItemID() (itemid uint64) {
	return 0
}

func (t *mockWatcherTask) Output() (output plugin.ResultWriter) {
	return t.resultSink
}

func (t *mockWatcherTask) Meta() (meta *plugin.Meta) {
	return nil
}

func (t *mockWatcherTask) GlobalRegexp() plugin.RegexpMatcher {
	return t.client.GlobalRegexp()
}

type mockConfigerTask struct {
	taskBase
	sink    chan performer
	options *agent.AgentOptions
}

func (t *mockConfigerTask) perform(s Scheduler) {
	t.plugin.impl.(plugin.Configurator).Configure(agent.GlobalOptions(t.options), t.options.Plugins[t.plugin.name()])
	t.sink <- t
}

func (t *mockConfigerTask) reschedule(now time.Time) (err error) {
	return
}

func (t *mockConfigerTask) getWeight() int {
	return t.plugin.maxCapacity
}

func checkExporterTasks(t *testing.T, m *Manager, clientID uint64, items []*clientItem) {
	lastCheck := time.Time{}
	n := 0
	for p := m.pluginQueue.Peek(); p != nil; p = m.pluginQueue.Peek() {
		if task := p.peekTask(); task != nil {
			if task.getScheduled().Before(lastCheck) {
				t.Errorf("Out of order tasks detected")
			}
			heap.Pop(&m.pluginQueue)
			p.popTask()
			n++
			if p.peekTask() != nil {
				heap.Push(&m.pluginQueue, p)
			}
		} else {
			heap.Pop(&m.pluginQueue)
		}
	}
	if len(items) != n {
		t.Errorf("Expected %d tasks while got %d", len(items), n)
	}

	var requestClient *client
	var ok bool
	if requestClient, ok = m.clients[clientID]; !ok {
		t.Errorf("Cannot find owner of the default client")
		return
	}

	for _, item := range items {
		if tacc, ok := requestClient.exporters[item.itemid]; ok {
			ti := tacc.task().item
			if ti.delay != item.delay {
				t.Errorf("Expected item %d delay %s while got %s", item.itemid, item.delay, ti.delay)
			}
			if ti.key != item.key {
				t.Errorf("Expected item %d key %s while got %s", item.itemid, item.key, ti.key)
			}
		} else {
			t.Errorf("Item %d was not queued", item.itemid)
		}
	}

	if len(items) != len(requestClient.exporters) {
		t.Errorf("Expected %d queued items while got %d", len(items), len(requestClient.exporters))
	}
}

func TestTaskCreate(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "", 0)

	plugin.ClearRegistry()
	plugins := make([]mockExporterPlugin, 3)
	for i := range plugins {
		p := &plugins[i]
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetrics(p, name, name, "Debug.")
	}

	manager, _ := NewManager(&agent.Options)

	items := []*clientItem{
		&clientItem{itemid: 1, delay: "151", key: "debug1"},
		&clientItem{itemid: 2, delay: "103", key: "debug2"},
		&clientItem{itemid: 3, delay: "79", key: "debug3"},
		&clientItem{itemid: 4, delay: "17", key: "debug1"},
		&clientItem{itemid: 5, delay: "7", key: "debug2"},
		&clientItem{itemid: 6, delay: "1", key: "debug3"},
		&clientItem{itemid: 7, delay: "63", key: "debug1"},
		&clientItem{itemid: 8, delay: "47", key: "debug2"},
		&clientItem{itemid: 9, delay: "31", key: "debug3"},
	}

	var cache resultCacheMock
	update := updateRequest{
		clientID: agent.MaxBuiltinClientID + 1,
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	var lastLogsize uint64
	var mtime int
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	manager.processUpdateRequest(&update, time.Now())

	if len(manager.pluginQueue) != 3 {
		t.Errorf("Expected %d plugins queued while got %d", 3, len(manager.pluginQueue))
	}

	checkExporterTasks(t, manager, agent.MaxBuiltinClientID+1, items)
}

func TestTaskUpdate(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "", 0)

	plugin.ClearRegistry()
	plugins := make([]mockExporterPlugin, 3)
	for i := range plugins {
		p := &plugins[i]
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetrics(p, name, name, "Debug.")
	}

	manager, _ := NewManager(&agent.Options)

	items := []*clientItem{
		&clientItem{itemid: 1, delay: "151", key: "debug1"},
		&clientItem{itemid: 2, delay: "103", key: "debug2"},
		&clientItem{itemid: 3, delay: "79", key: "debug3"},
		&clientItem{itemid: 4, delay: "17", key: "debug1"},
		&clientItem{itemid: 5, delay: "7", key: "debug2"},
		&clientItem{itemid: 6, delay: "1", key: "debug3"},
		&clientItem{itemid: 7, delay: "63", key: "debug1"},
		&clientItem{itemid: 8, delay: "47", key: "debug2"},
		&clientItem{itemid: 9, delay: "31", key: "debug3"},
	}

	var cache resultCacheMock
	update := updateRequest{
		clientID: agent.MaxBuiltinClientID + 1,
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	var lastLogsize uint64
	var mtime int
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	manager.processUpdateRequest(&update, time.Now())

	for _, item := range items {
		item.delay = "10" + item.delay
		item.key = item.key + "[1]"
	}
	update.requests = update.requests[:0]
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	manager.processUpdateRequest(&update, time.Now())

	if len(manager.pluginQueue) != 3 {
		t.Errorf("Expected %d plugins queued while got %d", 3, len(manager.pluginQueue))
	}

	checkExporterTasks(t, manager, agent.MaxBuiltinClientID+1, items)
}

func TestTaskUpdateInvalidInterval(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "", 0)

	plugin.ClearRegistry()
	plugins := make([]mockExporterPlugin, 3)
	for i := range plugins {
		p := &plugins[i]
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetrics(p, name, name, "Debug.")
	}

	manager, _ := NewManager(&agent.Options)

	items := []*clientItem{
		&clientItem{itemid: 1, delay: "151", key: "debug1"},
		&clientItem{itemid: 2, delay: "103", key: "debug2"},
	}

	var cache resultCacheMock
	update := updateRequest{
		clientID: agent.MaxBuiltinClientID + 1,
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	var lastLogsize uint64
	var mtime int
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	manager.processUpdateRequest(&update, time.Now())

	items[0].delay = "xyz"
	update.requests = update.requests[:0]
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	manager.processUpdateRequest(&update, time.Now())

	if len(manager.plugins["debug1"].tasks) != 0 {
		t.Errorf("Expected %d tasks queued while got %d", 0, len(manager.plugins["debug1"].tasks))
	}
}

func TestTaskDelete(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "", 0)

	plugin.ClearRegistry()
	plugins := make([]mockExporterPlugin, 3)
	for i := range plugins {
		p := &plugins[i]
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetrics(p, name, name, "Debug.")
	}

	manager, _ := NewManager(&agent.Options)

	items := []*clientItem{
		&clientItem{itemid: 1, delay: "151", key: "debug1"},
		&clientItem{itemid: 2, delay: "103", key: "debug2"},
		&clientItem{itemid: 3, delay: "79", key: "debug3"}, // remove
		&clientItem{itemid: 4, delay: "17", key: "debug1"},
		&clientItem{itemid: 5, delay: "7", key: "debug2"},
		&clientItem{itemid: 6, delay: "1", key: "debug3"}, // remove
		&clientItem{itemid: 7, delay: "63", key: "debug1"},
		&clientItem{itemid: 8, delay: "47", key: "debug2"}, // remove
		&clientItem{itemid: 9, delay: "31", key: "debug3"}, // remove
	}

	var cache resultCacheMock
	update := updateRequest{
		clientID: agent.MaxBuiltinClientID + 1,
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	var lastLogsize uint64
	var mtime int
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	manager.processUpdateRequest(&update, time.Now())

	items[2] = items[6]
	items = items[:cap(items)-4]
	update.requests = update.requests[:0]
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	manager.processUpdateRequest(&update, time.Now())

	if len(manager.plugins["debug3"].tasks) != 0 {
		t.Errorf("Expected %d tasks queued while got %d", 0, len(manager.plugins["debug3"].tasks))
	}

	checkExporterTasks(t, manager, agent.MaxBuiltinClientID+1, items)
}

func TestSchedule(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "", 0)

	manager := mockManager{sink: make(chan performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 3)
	for i := range plugins {
		plugins[i] = &mockExporterPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}}
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetrics(plugins[i], name, name, "Debug.")
	}
	manager.mockInit(t)

	items := []*clientItem{
		&clientItem{itemid: 1, delay: "1", key: "debug1"},
		&clientItem{itemid: 2, delay: "2", key: "debug2"},
		&clientItem{itemid: 3, delay: "5", key: "debug3"},
	}

	calls := []map[string][]int{
		map[string][]int{"debug1": []int{1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20}},
		map[string][]int{"debug2": []int{2, 4, 6, 8, 10, 12, 14, 16, 18, 20}},
		map[string][]int{"debug3": []int{5, 10, 15, 20}},
	}

	var cache resultCacheMock
	update := updateRequest{
		clientID: agent.MaxBuiltinClientID + 1,
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	var lastLogsize uint64
	var mtime int
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	manager.update(&update)
	manager.mockTasks()

	manager.iterate(t, 20)
	manager.checkPluginTimeline(t, plugins, calls, 20)
}

func TestScheduleCapacity(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "", 0)

	manager := mockManager{sink: make(chan performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 2)
	for i := range plugins {
		plugins[i] = &mockExporterPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}}
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetrics(plugins[i], name, name, "Debug.")
	}
	manager.mockInit(t)

	p := manager.plugins["debug2"]
	p.maxCapacity = 2

	items := []*clientItem{
		&clientItem{itemid: 1, delay: "1", key: "debug1"},
		&clientItem{itemid: 2, delay: "2", key: "debug2"},
		&clientItem{itemid: 3, delay: "2", key: "debug2"},
		&clientItem{itemid: 4, delay: "2", key: "debug2"},
	}

	calls := []map[string][]int{
		map[string][]int{"debug1": []int{1, 2, 3, 4, 5, 6, 7, 8, 9, 10}},
		map[string][]int{"debug2": []int{2, 2, 3, 4, 4, 5, 6, 6, 7, 8, 8, 9, 10, 10}},
	}

	var cache resultCacheMock
	update := updateRequest{
		clientID: agent.MaxBuiltinClientID + 1,
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	var lastLogsize uint64
	var mtime int
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	manager.update(&update)
	manager.mockTasks()

	manager.iterate(t, 10)
	manager.checkPluginTimeline(t, plugins, calls, 10)
}

func TestScheduleUpdate(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "", 0)

	manager := mockManager{sink: make(chan performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 3)
	for i := range plugins {
		plugins[i] = &mockExporterPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}}
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetrics(plugins[i], name, name, "Debug.")
	}
	manager.mockInit(t)

	items := []*clientItem{
		&clientItem{itemid: 1, delay: "1", key: "debug1"},
		&clientItem{itemid: 2, delay: "1", key: "debug2"},
		&clientItem{itemid: 3, delay: "1", key: "debug3"},
	}

	calls := []map[string][]int{
		map[string][]int{"debug1": []int{1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 16, 17, 18, 19, 20}},
		map[string][]int{"debug2": []int{1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 16, 17, 18, 19, 20}},
		map[string][]int{"debug3": []int{1, 2, 3, 4, 5, 16, 17, 18, 19, 20}},
	}

	var cache resultCacheMock
	update := updateRequest{
		clientID: agent.MaxBuiltinClientID + 1,
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	var lastLogsize uint64
	var mtime int
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[:2]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[:0]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[:3]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)
}

func TestCollectorSchedule(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "", 0)

	manager := mockManager{sink: make(chan performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 1)
	for i := range plugins {
		plugins[i] = &mockCollectorPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}, period: 2}
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetrics(plugins[i], name, name, "Debug.")
	}
	manager.mockInit(t)

	items := []*clientItem{
		&clientItem{itemid: 1, delay: "1", key: "debug1"},
	}

	calls := []map[string][]int{
		map[string][]int{"$collect": []int{2, 4, 6, 8, 10, 12, 14, 16, 18, 20}},
	}

	var cache resultCacheMock
	update := updateRequest{
		clientID: agent.MaxBuiltinClientID + 1,
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	var lastLogsize uint64
	var mtime int
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 20)
	manager.checkPluginTimeline(t, plugins, calls, 20)
}

func TestCollectorScheduleUpdate(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "", 0)

	manager := mockManager{sink: make(chan performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 3)
	for i := range plugins {
		plugins[i] = &mockCollectorPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}, period: 2}
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetrics(plugins[i], name, name, "Debug.")
	}
	manager.mockInit(t)

	items := []*clientItem{
		&clientItem{itemid: 1, delay: "5", key: "debug1"},
		&clientItem{itemid: 2, delay: "5", key: "debug2"},
		&clientItem{itemid: 3, delay: "5", key: "debug3"},
	}

	calls := []map[string][]int{
		map[string][]int{"$collect": []int{2, 4, 6, 8, 10, 12, 14}},
		map[string][]int{"$collect": []int{2, 4, 6, 8, 10, 22, 24}},
		map[string][]int{"$collect": []int{2, 4, 22, 24}},
	}

	var cache resultCacheMock
	update := updateRequest{
		clientID: agent.MaxBuiltinClientID + 1,
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	var lastLogsize uint64
	var mtime int
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[:2]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[:1]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[:0]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[1:3]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)
}

func TestRunner(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "", 0)

	manager := mockManager{sink: make(chan performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 3)
	for i := range plugins {
		plugins[i] = &mockRunnerPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}}
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetrics(plugins[i], name, name, "Debug.")
	}
	manager.mockInit(t)

	items := []*clientItem{
		&clientItem{itemid: 1, delay: "5", key: "debug1"},
		&clientItem{itemid: 2, delay: "5", key: "debug2"},
		&clientItem{itemid: 3, delay: "5", key: "debug3"},
	}

	calls := []map[string][]int{
		map[string][]int{"$start": []int{1, 5}, "$stop": []int{4, 6}},
		map[string][]int{"$start": []int{1, 5, 7}, "$stop": []int{3, 6, 8}},
		map[string][]int{"$start": []int{1, 5, 8}, "$stop": []int{2, 6}},
	}

	var cache resultCacheMock
	update := updateRequest{
		clientID: agent.MaxBuiltinClientID + 1,
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	var lastLogsize uint64
	var mtime int
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	update.requests = update.requests[:2]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	update.requests = update.requests[:1]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	update.requests = update.requests[:0]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	update.requests = update.requests[:3]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	update.requests = update.requests[:0]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	update.requests = update.requests[1:2]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	update.requests = update.requests[1:2]
	manager.update(&update)
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
		t.Errorf("Expected watch requests %s while got %s", expected, returned)
	}
}

func TestWatcher(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "", 0)

	manager := mockManager{sink: make(chan performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 3)
	for i := range plugins {
		plugins[i] = &mockWatcherPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}}
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetrics(plugins[i], name, name, "Debug.")
	}
	manager.mockInit(t)

	items := []*clientItem{
		&clientItem{itemid: 1, delay: "5", key: "debug1"},
		&clientItem{itemid: 2, delay: "5", key: "debug2[1]"},
		&clientItem{itemid: 3, delay: "5", key: "debug2[2]"},
		&clientItem{itemid: 4, delay: "5", key: "debug3[1]"},
		&clientItem{itemid: 5, delay: "5", key: "debug3[2]"},
		&clientItem{itemid: 6, delay: "5", key: "debug3[3]"},
	}

	calls := []map[string][]int{
		map[string][]int{"$watch": []int{1, 2, 3, 4, 5}},
		map[string][]int{"$watch": []int{1, 2, 3, 4, 5}},
		map[string][]int{"$watch": []int{1, 2, 3, 5}},
	}

	var cache resultCacheMock
	update := updateRequest{
		clientID: agent.MaxBuiltinClientID + 1,
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	var lastLogsize uint64
	var mtime int
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	checkWatchRequests(t, plugins[0], update.requests[0:1])
	checkWatchRequests(t, plugins[1], update.requests[1:3])
	checkWatchRequests(t, plugins[2], update.requests[3:6])

	update.requests = update.requests[:5]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	checkWatchRequests(t, plugins[0], update.requests[0:1])
	checkWatchRequests(t, plugins[1], update.requests[1:3])
	checkWatchRequests(t, plugins[2], update.requests[3:5])

	update.requests = update.requests[:3]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	checkWatchRequests(t, plugins[0], update.requests[0:1])
	checkWatchRequests(t, plugins[1], update.requests[1:3])

	update.requests = update.requests[:2]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	checkWatchRequests(t, plugins[0], update.requests[0:1])
	checkWatchRequests(t, plugins[1], update.requests[1:2])

	update.requests = update.requests[:6]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)

	checkWatchRequests(t, plugins[0], update.requests[0:1])
	checkWatchRequests(t, plugins[1], update.requests[1:3])
	checkWatchRequests(t, plugins[2], update.requests[3:6])
}

func TestCollectorExporterSchedule(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "", 0)

	manager := mockManager{sink: make(chan performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 1)
	for i := range plugins {
		plugins[i] = &mockCollectorExporterPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}, period: 2}
		plugin.RegisterMetrics(plugins[i], "debug", "debug", "Debug.")
	}
	manager.mockInit(t)

	items := []*clientItem{
		&clientItem{itemid: 1, delay: "2", key: "debug[1]"},
		&clientItem{itemid: 2, delay: "2", key: "debug[2]"},
		&clientItem{itemid: 3, delay: "2", key: "debug[3]"},
	}

	calls := []map[string][]int{
		map[string][]int{"debug": []int{3, 3, 3, 5, 5, 5, 7, 7, 7, 9, 9, 9}, "$collect": []int{2, 4, 6, 8, 10}},
	}

	var cache resultCacheMock
	update := updateRequest{
		clientID: agent.MaxBuiltinClientID + 1,
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	var lastLogsize uint64
	var mtime int
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 10)

	manager.checkPluginTimeline(t, plugins, calls, 10)
}

func TestRunnerWatcher(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "", 0)

	manager := mockManager{sink: make(chan performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 3)
	for i := range plugins {
		plugins[i] = &mockRunnerWatcherPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}}
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetrics(plugins[i], name, name, "Debug.")
	}
	manager.mockInit(t)

	items := []*clientItem{
		&clientItem{itemid: 1, delay: "5", key: "debug1"},
		&clientItem{itemid: 2, delay: "5", key: "debug2[1]"},
		&clientItem{itemid: 3, delay: "5", key: "debug2[2]"},
		&clientItem{itemid: 4, delay: "5", key: "debug3[1]"},
		&clientItem{itemid: 5, delay: "5", key: "debug3[2]"},
		&clientItem{itemid: 6, delay: "5", key: "debug3[3]"},
	}

	calls := []map[string][]int{
		map[string][]int{"$watch": []int{2, 6, 11, 16}, "$start": []int{1}, "$stop": []int{17}},
		map[string][]int{"$watch": []int{2, 6, 11, 22, 26}, "$start": []int{1, 21}, "$stop": []int{12, 27}},
		map[string][]int{"$watch": []int{2, 6, 27}, "$start": []int{1, 26}, "$stop": []int{7}},
	}

	var cache resultCacheMock
	update := updateRequest{
		clientID: agent.MaxBuiltinClientID + 1,
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	var lastLogsize uint64
	var mtime int
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	checkWatchRequests(t, plugins[0], update.requests[0:1])
	checkWatchRequests(t, plugins[1], update.requests[1:3])
	checkWatchRequests(t, plugins[2], update.requests[3:6])

	update.requests = update.requests[:3]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	checkWatchRequests(t, plugins[0], update.requests[0:1])
	checkWatchRequests(t, plugins[1], update.requests[1:3])

	update.requests = update.requests[:1]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	checkWatchRequests(t, plugins[0], update.requests[0:1])

	update.requests = update.requests[:0]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[1:3]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	checkWatchRequests(t, plugins[1], update.requests[:2])

	update.requests = update.requests[2:5]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	checkWatchRequests(t, plugins[2], update.requests[0:3])
}

func TestMultiCollectorExporterSchedule(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "", 0)

	manager := mockManager{sink: make(chan performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 1)
	for i := range plugins {
		plugins[i] = &mockCollectorExporterPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}, period: 2}
		plugin.RegisterMetrics(plugins[i], "debug", "debug", "Debug.")
	}
	manager.mockInit(t)

	items := []*clientItem{
		&clientItem{itemid: 1, delay: "2", key: "debug[1]"},
	}

	calls := []map[string][]int{
		map[string][]int{"debug": []int{3, 3, 5, 5, 7, 9}, "$collect": []int{2, 4, 6, 8, 10}},
	}

	var cache resultCacheMock
	update := updateRequest{
		clientID: agent.MaxBuiltinClientID + 1,
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	var lastLogsize uint64
	var mtime int
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	manager.update(&update)
	update.clientID = agent.MaxBuiltinClientID + 2
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[:0]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.clientID = agent.MaxBuiltinClientID + 1
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)
}

func TestMultiRunnerWatcher(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "", 0)

	manager := mockManager{sink: make(chan performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 1)
	for i := range plugins {
		plugins[i] = &mockRunnerWatcherPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}}
		plugin.RegisterMetrics(plugins[i], "debug", "debug", "Debug.")
	}
	manager.mockInit(t)

	items := []*clientItem{
		&clientItem{itemid: 1, delay: "5", key: "debug[1]"},
		&clientItem{itemid: 2, delay: "5", key: "debug[2]"},
		&clientItem{itemid: 3, delay: "5", key: "debug[3]"},
	}

	calls := []map[string][]int{
		map[string][]int{"$watch": []int{2, 3, 6, 7, 11, 17, 21}, "$start": []int{1, 16}, "$stop": []int{12}},
	}

	var cache resultCacheMock
	update := updateRequest{
		clientID: agent.MaxBuiltinClientID + 1,
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	var lastLogsize uint64
	var mtime int
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	manager.update(&update)
	update.clientID = agent.MaxBuiltinClientID + 2
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.clientID = agent.MaxBuiltinClientID + 1
	manager.update(&update)
	update.clientID = agent.MaxBuiltinClientID + 2
	update.requests = update.requests[:0]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.clientID = agent.MaxBuiltinClientID + 1
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[:1]
	update.clientID = agent.MaxBuiltinClientID + 2
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.clientID = agent.MaxBuiltinClientID + 1
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)
}

func TestPassiveRunner(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "", 0)

	manager := mockManager{sink: make(chan performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 3)
	for i := range plugins {
		plugins[i] = &mockPassiveRunnerPlugin{Base: plugin.Base{}, mockPlugin: mockPlugin{now: &manager.now}}
		name := fmt.Sprintf("debug%d", i+1)
		plugin.RegisterMetrics(plugins[i], name, name, "Debug.")
	}
	manager.mockInit(t)

	items := []*clientItem{
		&clientItem{itemid: 1, delay: "5", key: "debug1"},
		&clientItem{itemid: 2, delay: "5", key: "debug2"},
		&clientItem{itemid: 3, delay: "5", key: "debug3"},
	}

	calls := []map[string][]int{
		map[string][]int{"$start": []int{1}, "$stop": []int{}},
		map[string][]int{"$start": []int{1}, "$stop": []int{3600*51 + 1}},
		map[string][]int{"$start": []int{1}, "$stop": []int{3600*26 + 1}},
	}

	var cache resultCacheMock
	update := updateRequest{
		clientID: agent.PassiveChecksClientID,
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	var lastLogsize uint64
	var mtime int
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 3600)
	manager.checkPluginTimeline(t, plugins, calls, 3600)

	update.requests = update.requests[:0]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 3600)
	manager.checkPluginTimeline(t, plugins, calls, 3600)

	update.requests = update.requests[:2]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 3600*24)
	manager.checkPluginTimeline(t, plugins, calls, 3600*24)

	update.requests = update.requests[:1]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 3600*25)
	manager.checkPluginTimeline(t, plugins, calls, 3600*25)

	update.requests = update.requests[:1]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 1)
	manager.checkPluginTimeline(t, plugins, calls, 1)
}

type configuratorOption struct {
	Params interface{} `conf:"optional"`
}

func TestConfigurator(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "", 0)

	var opt1, opt2, opt3 configuratorOption
	_ = conf.Unmarshal([]byte("Delay=5"), &opt1)
	_ = conf.Unmarshal([]byte("Delay=30"), &opt2)
	_ = conf.Unmarshal([]byte("Delay=60"), &opt3)

	agent.Options.Plugins = map[string]interface{}{
		"Debug1": opt1.Params,
		"Debug2": opt2.Params,
		"Debug3": opt3.Params,
	}

	manager := mockManager{sink: make(chan performer, 10)}
	plugin.ClearRegistry()
	plugins := make([]plugin.Accessor, 3)
	for i := range plugins {
		name := fmt.Sprintf("debug%d", i+1)
		plugins[i] = &mockConfiguratorPlugin{
			Base:       plugin.Base{},
			mockPlugin: mockPlugin{now: &manager.now},
			options:    agent.Options.Plugins[name]}
		plugin.RegisterMetrics(plugins[i], name, name, "Debug.")
	}
	manager.mockInit(t)

	items := []*clientItem{
		&clientItem{itemid: 1, delay: "5", key: "debug1"},
		&clientItem{itemid: 2, delay: "5", key: "debug2"},
		&clientItem{itemid: 3, delay: "5", key: "debug3"},
	}

	calls := []map[string][]int{
		map[string][]int{"$configure": []int{1}},
		map[string][]int{"$configure": []int{6}},
		map[string][]int{"$configure": []int{11}},
	}

	var cache resultCacheMock
	update := updateRequest{
		clientID: agent.MaxBuiltinClientID + 1,
		sink:     &cache,
		requests: make([]*plugin.Request, 0),
	}

	var lastLogsize uint64
	var mtime int
	for _, item := range items {
		update.requests = append(update.requests, &plugin.Request{
			Itemid:      item.itemid,
			Key:         item.key,
			Delay:       item.delay,
			LastLogsize: &lastLogsize,
			Mtime:       &mtime,
		})
	}
	update.requests = update.requests[:1]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[:2]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)

	update.requests = update.requests[:3]
	manager.update(&update)
	manager.mockTasks()
	manager.iterate(t, 5)
	manager.checkPluginTimeline(t, plugins, calls, 5)
}

func Test_getCapacity(t *testing.T) {
	type args struct {
		optsRaw interface{}
	}
	tests := []struct {
		name string
		args args
		want int
	}{
		{
			"default",
			args{
				&conf.Node{
					Name:  "Test",
					Nodes: []interface{}{},
				},
			},
			100,
		},
		{
			"both_cap",
			args{
				&conf.Node{
					Name: "Test",
					Nodes: []interface{}{
						&conf.Node{
							Name: "Capacity",
							Nodes: []interface{}{
								&conf.Value{Value: []byte("10")},
							},
						},
						&conf.Node{
							Name: "System",
							Nodes: []interface{}{
								&conf.Node{
									Name: "Capacity",
									Nodes: []interface{}{
										&conf.Value{Value: []byte("50")},
									},
								},
							},
						},
					},
				},
			},
			50,
		},
		{
			"depriceted_cap",
			args{
				&conf.Node{
					Name: "Test",
					Nodes: []interface{}{
						&conf.Node{
							Name: "Capacity",
							Nodes: []interface{}{
								&conf.Value{Value: []byte("10")},
							},
						},
					},
				},
			},
			10,
		},
		{
			"system_cap",
			args{
				&conf.Node{
					Name: "Test",
					Nodes: []interface{}{
						&conf.Node{
							Name: "System",
							Nodes: []interface{}{
								&conf.Node{
									Name: "Capacity",
									Nodes: []interface{}{
										&conf.Value{Value: []byte("50")},
									},
								},
							},
						},
					},
				},
			},
			50,
		},
		{
			"nil",
			args{nil},
			100,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got, _ := getPluginOptions(tt.args.optsRaw, "test"); got != tt.want {
				t.Errorf("getCapacity() = %v, want %v", got, tt.want)
			}
		})
	}
}
