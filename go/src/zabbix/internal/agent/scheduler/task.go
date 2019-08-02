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
	"reflect"
	"time"
	"zabbix/internal/plugin"
	"zabbix/pkg/itemutil"
	"zabbix/pkg/log"
)

// task priority within the same second is done by setting nanosecond component
const (
	priorityConfiguratorTaskNs = iota
	priorityStarterTaskNs
	priorityCollectorTaskNs
	priorityWatcherTaskNs
	priorityExporterTaskNs
	priorityStopperTaskNs
)

type taskBase struct {
	plugin    *pluginAgent
	scheduled time.Time
	index     int
	active    bool
}

func (t *taskBase) getPlugin() *pluginAgent {
	return t.plugin
}

func (t *taskBase) getScheduled() time.Time {
	return t.scheduled
}

func (t *taskBase) getWeight() int {
	return 1
}

func (t *taskBase) getIndex() int {
	return t.index
}

func (t *taskBase) setIndex(index int) {
	t.index = index
}

func (t *taskBase) deactivate() {
	if t.index != -1 {
		t.plugin.removeTask(t.index)
	}
	t.active = false
}

func (t *taskBase) isActive() bool {
	return t.active
}

func (t *taskBase) finish() bool {
	return false
}

type collectorTask struct {
	taskBase
	seed uint64
}

func (t *collectorTask) perform(s Scheduler) {
	go func() {
		collector, _ := t.plugin.impl.(plugin.Collector)
		if err := collector.Collect(); err != nil {
			log.Warningf("Plugin '%s' collector failed: %s", t.plugin.impl.Name(), err.Error())
		}
		s.FinishTask(t)
	}()
}

func (t *collectorTask) reschedule(now time.Time) {
	collector, _ := t.plugin.impl.(plugin.Collector)
	t.scheduled = time.Unix(now.Unix()+int64(t.seed)%int64(collector.Period())+1, priorityCollectorTaskNs)
}

func (t *collectorTask) getWeight() int {
	return t.plugin.capacity
}

func (t *collectorTask) finish() bool {
	return true
}

type exporterTask struct {
	taskBase
	writer      plugin.ResultWriter
	item        *clientItem
	unsupported bool
}

func (t *exporterTask) perform(s Scheduler) {
	go func(itemkey string) {
		exporter, _ := t.plugin.impl.(plugin.Exporter)
		now := time.Now()
		var key string
		var params []string
		var err error
		if key, params, err = itemutil.ParseKey(itemkey); err == nil {
			var ret interface{}
			if ret, err = exporter.Export(key, params); err == nil {
				if ret != nil {
					rt := reflect.TypeOf(ret)
					switch rt.Kind() {
					case reflect.Slice:
						fallthrough
					case reflect.Array:
						s := reflect.ValueOf(ret)
						for i := 0; i < s.Len(); i++ {
							value := itemutil.ValueToString(s.Index(i))
							t.writer.Write(&plugin.Result{Itemid: t.item.itemid, Value: &value, Ts: now})
						}
					default:
						value := itemutil.ValueToString(ret)
						t.writer.Write(&plugin.Result{Itemid: t.item.itemid, Value: &value, Ts: now})
					}
				} else {
					t.writer.Write(&plugin.Result{Itemid: t.item.itemid, Ts: now})
				}
			}
		}
		if err != nil {
			t.writer.Write(&plugin.Result{Itemid: t.item.itemid, Error: err, Ts: now})
			t.unsupported = true
		} else {
			t.unsupported = false
		}
		s.FinishTask(t)
	}(t.item.key)
}

func (t *exporterTask) reschedule(now time.Time) {
	if t.item.itemid != 0 {
		t.scheduled, _ = itemutil.GetNextcheck(t.item.itemid, t.item.delay, t.item.unsupported, now)
	} else {
		t.scheduled = time.Unix(now.Unix(), priorityExporterTaskNs)
	}
}

func (t *exporterTask) finish() bool {
	// direct metric requests are one time checks
	if t.item.itemid == 0 {
		return false
	}
	t.item.unsupported = t.unsupported
	return true
}

type starterTask struct {
	taskBase
}

func (t *starterTask) perform(s Scheduler) {
	go func() {
		runner, _ := t.plugin.impl.(plugin.Runner)
		runner.Start()
		s.FinishTask(t)
	}()
}

func (t *starterTask) reschedule(now time.Time) {
	t.scheduled = time.Unix(now.Unix(), priorityStarterTaskNs)
}

func (t *starterTask) getWeight() int {
	return t.plugin.capacity
}

type stopperTask struct {
	taskBase
}

func (t *stopperTask) perform(s Scheduler) {
	go func() {
		runner, _ := t.plugin.impl.(plugin.Runner)
		runner.Stop()
		s.FinishTask(t)
	}()
}

func (t *stopperTask) reschedule(now time.Time) {
	t.scheduled = time.Unix(now.Unix(), priorityStopperTaskNs)
}

func (t *stopperTask) getWeight() int {
	return t.plugin.capacity
}

type watcherTask struct {
	taskBase
	requests []*plugin.Request
	sink     plugin.ResultWriter
}

func (t *watcherTask) perform(s Scheduler) {
	go func() {
		watcher, _ := t.plugin.impl.(plugin.Watcher)
		watcher.Watch(t.requests, t.sink)
		s.FinishTask(t)
	}()
}

func (t *watcherTask) reschedule(now time.Time) {
	t.scheduled = time.Unix(now.Unix(), priorityWatcherTaskNs)
}

func (t *watcherTask) getWeight() int {
	return t.plugin.capacity
}

type configerTask struct {
	taskBase
	options map[string]string
}

func (t *configerTask) perform(s Scheduler) {
	go func() {
		config, _ := t.plugin.impl.(plugin.Configurator)
		config.Configure(t.options)
		s.FinishTask(t)
	}()
}

func (t *configerTask) reschedule(now time.Time) {
	t.scheduled = time.Unix(now.Unix(), priorityConfiguratorTaskNs)
}

func (t *configerTask) getWeight() int {
	return t.plugin.capacity
}
