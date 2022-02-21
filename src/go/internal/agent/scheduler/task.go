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
	"errors"
	"fmt"
	"reflect"
	"time"

	"zabbix.com/internal/agent"
	"zabbix.com/pkg/itemutil"
	"zabbix.com/pkg/log"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/zbxlib"
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

// exporterTaskAccessor is used by clients to track item exporter tasks .
type exporterTaskAccessor interface {
	task() *exporterTask
}

// taskBase implements common task properties and functionality
type taskBase struct {
	plugin    *pluginAgent
	scheduled time.Time
	index     int
	active    bool
	recurring bool
}

func (t *taskBase) getPlugin() *pluginAgent {
	return t.plugin
}

func (t *taskBase) setPlugin(p *pluginAgent) {
	t.plugin = p
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

func (t *taskBase) isRecurring() bool {
	return t.recurring
}

func (t *taskBase) isItemKeyEqual(itemkey string) bool {
	return false
}

// collectorTask provides access to plugin Collector interaface.
type collectorTask struct {
	taskBase
	seed uint64
}

func (t *collectorTask) perform(s Scheduler) {
	log.Debugf("plugin %s: executing collector task", t.plugin.name())
	go func() {
		collector, _ := t.plugin.impl.(plugin.Collector)
		if err := collector.Collect(); err != nil {
			log.Warningf("plugin '%s' collector failed: %s", t.plugin.impl.Name(), err.Error())
		}
		s.FinishTask(t)
	}()
}

func (t *collectorTask) reschedule(now time.Time) (err error) {
	collector, _ := t.plugin.impl.(plugin.Collector)
	period := int64(collector.Period())
	if period == 0 {
		return fmt.Errorf("invalid collector interval 0 seconds")
	}
	seconds := now.Unix()
	nextcheck := period*(seconds/period) + int64(t.seed)%period
	for nextcheck <= seconds {
		nextcheck += period
	}
	t.scheduled = time.Unix(nextcheck, priorityCollectorTaskNs)
	return
}

func (t *collectorTask) getWeight() int {
	return t.plugin.maxCapacity
}

func (t *collectorTask) isItemKeyEqual(itemkey string) bool {
	return false
}

// exporterTask provides access to plugin Exporter interaface. It's used
// for active check items.
type exporterTask struct {
	taskBase
	item    clientItem
	failed  bool
	updated time.Time
	client  ClientAccessor
	meta    plugin.Meta
	output  plugin.ResultWriter
}

func (t *exporterTask) perform(s Scheduler) {
	// pass item key as parameter so it can be safely updated while task is being processed in its goroutine
	go func(itemkey string) {
		var result *plugin.Result
		exporter, _ := t.plugin.impl.(plugin.Exporter)
		now := time.Now()
		var key string
		var params []string
		var err error

		if key, params, err = itemutil.ParseKey(itemkey); err == nil {
			var ret interface{}
			log.Debugf("executing exporter task for itemid:%d key '%s'", t.item.itemid, itemkey)

			if ret, err = exporter.Export(key, params, t); err == nil {
				log.Debugf("executed exporter task for itemid:%d key '%s'", t.item.itemid, itemkey)
				if ret != nil {
					rt := reflect.TypeOf(ret)
					switch rt.Kind() {
					case reflect.Slice:
						fallthrough
					case reflect.Array:
						s := reflect.ValueOf(ret)
						for i := 0; i < s.Len(); i++ {
							result = itemutil.ValueToResult(t.item.itemid, now, s.Index(i).Interface())
							t.output.Write(result)
						}
					default:
						result = itemutil.ValueToResult(t.item.itemid, now, ret)
						t.output.Write(result)
					}
				}
			} else {
				log.Debugf("failed to execute exporter task for itemid:%d key '%s' error: '%s'",
					t.item.itemid, itemkey, err.Error())
			}
		}
		if err != nil {
			result = &plugin.Result{Itemid: t.item.itemid, Error: err, Ts: now}
			t.output.Write(result)
		}
		// set failed state based on last result
		if result != nil && result.Error != nil {
			log.Warningf(`check '%s' is not supported: %s`, itemkey, result.Error)
			t.failed = true
		} else {
			t.failed = false
		}

		s.FinishTask(t)
	}(t.item.key)
}

func (t *exporterTask) reschedule(now time.Time) (err error) {
	var nextcheck time.Time
	nextcheck, _, err = zbxlib.GetNextcheck(t.item.itemid, t.item.delay, now)
	if err != nil {
		return
	}
	t.scheduled = nextcheck.Add(priorityExporterTaskNs)
	return
}

func (t *exporterTask) task() (task *exporterTask) {
	return t
}

// plugin.ContextProvider interface

func (t *exporterTask) ClientID() (clientid uint64) {
	return t.client.ID()
}

func (t *exporterTask) Output() (output plugin.ResultWriter) {
	return t.output
}

func (t *exporterTask) ItemID() (itemid uint64) {
	return t.item.itemid
}

func (t *exporterTask) isItemKeyEqual(itemkey string) bool {
	return t.item.key == itemkey
}

func (t *exporterTask) Meta() (meta *plugin.Meta) {
	return &t.meta
}

func (t *exporterTask) GlobalRegexp() plugin.RegexpMatcher {
	return t.client.GlobalRegexp()
}

// directExporterTask provides access to plugin Exporter interaface.
// It's used for non-recurring exporter requests - single passive checks
// and internal requests to obtain HostnameItem, HostMetadataItem,
// HostInterfaceItem etc values.
type directExporterTask struct {
	taskBase
	item   clientItem
	done   bool
	expire time.Time
	client ClientAccessor
	meta   plugin.Meta
	output plugin.ResultWriter
}

func (t *directExporterTask) isRecurring() bool {
	return !t.done
}
func (t *directExporterTask) perform(s Scheduler) {
	// pass item key as parameter so it can be safely updated while task is being processed in its goroutine
	go func(itemkey string) {
		var result *plugin.Result
		exporter, _ := t.plugin.impl.(plugin.Exporter)
		now := time.Now()
		var key string
		var params []string
		var err error

		if now.After(t.expire) {
			err = errors.New("No data available.")
			log.Debugf("direct exporter task expired for key '%s' error: '%s'", itemkey, err.Error())
		} else {
			if key, params, err = itemutil.ParseKey(itemkey); err == nil {
				var ret interface{}
				log.Debugf("executing direct exporter task for key '%s'", itemkey)

				if ret, err = exporter.Export(key, params, t); err == nil {
					log.Debugf("executed direct exporter task for key '%s'", itemkey)
					if ret != nil {
						rt := reflect.TypeOf(ret)
						switch rt.Kind() {
						case reflect.Slice, reflect.Array:
							err = errors.New("Multiple return values are not supported for single passive checks")
						default:
							result = itemutil.ValueToResult(t.item.itemid, now, ret)
							t.output.Write(result)
							t.done = true
						}
					}
				} else {
					log.Debugf("failed to execute direct exporter task for key '%s' error: '%s'",
						itemkey, err.Error())
				}
			}
		}
		if err != nil {
			result = &plugin.Result{Itemid: t.item.itemid, Error: err, Ts: now}
			t.output.Write(result)
			t.done = true
		}

		s.FinishTask(t)
	}(t.item.key)
}

func (t *directExporterTask) reschedule(now time.Time) (err error) {
	if t.scheduled.IsZero() {
		t.scheduled = time.Unix(now.Unix(), priorityExporterTaskNs)
	} else {
		t.scheduled = time.Unix(now.Unix()+1, priorityExporterTaskNs)
	}
	return
}

// plugin.ContextProvider interface

func (t *directExporterTask) ClientID() (clientid uint64) {
	return t.client.ID()
}

func (t *directExporterTask) Output() (output plugin.ResultWriter) {
	return t.output
}

func (t *directExporterTask) ItemID() (itemid uint64) {
	return t.item.itemid
}

func (t *directExporterTask) isItemKeyEqual(itemkey string) bool {
	return t.item.key == itemkey
}

func (t *directExporterTask) Meta() (meta *plugin.Meta) {
	return &t.meta
}

func (t *directExporterTask) GlobalRegexp() plugin.RegexpMatcher {
	return t.client.GlobalRegexp()
}

// starterTask provides access to plugin Exporter interaface Start() method.
type starterTask struct {
	taskBase
}

func (t *starterTask) perform(s Scheduler) {
	log.Debugf("plugin %s: executing starter task", t.plugin.name())
	go func() {
		runner, _ := t.plugin.impl.(plugin.Runner)
		runner.Start()
		s.FinishTask(t)
	}()
}

func (t *starterTask) reschedule(now time.Time) (err error) {
	t.scheduled = time.Unix(now.Unix(), priorityStarterTaskNs)
	return
}

func (t *starterTask) getWeight() int {
	return t.plugin.maxCapacity
}

func (t *starterTask) isItemKeyEqual(itemkey string) bool {
	return false
}

// stopperTask provides access to plugin Exporter interaface Start() method.
type stopperTask struct {
	taskBase
}

func (t *stopperTask) perform(s Scheduler) {
	log.Debugf("plugin %s: executing stopper task", t.plugin.name())
	go func() {
		runner, _ := t.plugin.impl.(plugin.Runner)
		runner.Stop()
		s.FinishTask(t)
	}()
}

func (t *stopperTask) reschedule(now time.Time) (err error) {
	t.scheduled = time.Unix(now.Unix(), priorityStopperTaskNs)
	return
}

func (t *stopperTask) getWeight() int {
	return t.plugin.maxCapacity
}

func (t *stopperTask) isItemKeyEqual(itemkey string) bool {
	return false
}

// stopperTask provides access to plugin Watcher interaface.
type watcherTask struct {
	taskBase
	requests []*plugin.Request
	client   ClientAccessor
}

func (t *watcherTask) perform(s Scheduler) {
	log.Debugf("plugin %s: executing watcher task", t.plugin.name())
	go func() {
		watcher, _ := t.plugin.impl.(plugin.Watcher)
		watcher.Watch(t.requests, t)
		s.FinishTask(t)
	}()
}

func (t *watcherTask) reschedule(now time.Time) (err error) {
	t.scheduled = time.Unix(now.Unix(), priorityWatcherTaskNs)
	return
}

func (t *watcherTask) getWeight() int {
	return t.plugin.maxCapacity
}

// plugin.ContextProvider interface

func (t *watcherTask) ClientID() (clientid uint64) {
	return t.client.ID()
}

func (t *watcherTask) Output() (output plugin.ResultWriter) {
	return t.client.Output()
}

func (t *watcherTask) ItemID() (itemid uint64) {
	return 0
}

func (t *watcherTask) isItemKeyEqual(itemkey string) bool {
	return false
}

func (t *watcherTask) Meta() (meta *plugin.Meta) {
	return nil
}

func (t *watcherTask) GlobalRegexp() plugin.RegexpMatcher {
	return t.client.GlobalRegexp()
}

// configuratorTask provides access to plugin Configurator interaface.
type configuratorTask struct {
	taskBase
	options *agent.AgentOptions
}

func (t *configuratorTask) perform(s Scheduler) {
	log.Debugf("plugin %s: executing configurator task", t.plugin.name())
	go func() {
		config, _ := t.plugin.impl.(plugin.Configurator)
		config.Configure(agent.GlobalOptions(t.options), t.options.Plugins[t.plugin.name()])
		s.FinishTask(t)
	}()
}

func (t *configuratorTask) reschedule(now time.Time) (err error) {
	t.scheduled = time.Unix(now.Unix(), priorityConfiguratorTaskNs)
	return
}

func (t *configuratorTask) getWeight() int {
	return t.plugin.maxCapacity
}

func (t *configuratorTask) isItemKeyEqual(itemkey string) bool {
	return false
}
