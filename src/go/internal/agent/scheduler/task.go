/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

package scheduler

import (
	"errors"
	"fmt"
	"reflect"
	"time"

	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/internal/agent/resultcache"
	"golang.zabbix.com/agent2/pkg/itemutil"
	"golang.zabbix.com/agent2/pkg/zbxlib"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin"
)

// task priority within the same second is done by setting nanosecond component
const (
	priorityConfiguratorTaskNs = iota
	priorityCommandTaskNs      = iota
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
		start := time.Now()
		err := collector.Collect()

		if err != nil {
			log.Warningf("plugin '%s' collector failed: %s", t.plugin.impl.Name(), err.Error())
		}

		elapsedSeconds := time.Since(start).Seconds()

		if elapsedSeconds > float64(collector.Period()) {
			log.Warningf(
				"plugin '%s': time spent in collector task %f s exceeds collecting interval %d s",
				t.plugin.impl.Name(),
				elapsedSeconds,
				collector.Period(),
			)
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

func invokeExport(a plugin.Accessor, key string, params []string, ctx plugin.ContextProvider) (any, error) {
	exporter, _ := a.(plugin.Exporter)
	timeout := ctx.Timeout()

	if a.HandleTimeout() {
		timeout = maxItemTimeout
	}

	var ret any
	var err error
	tc := make(chan bool)

	go func() {
		ret, err = exporter.Export(key, params, ctx)
		tc <- true
	}()

	select {
	case <-tc:
		return ret, err //nolint:wrapcheck
	case <-time.After(time.Second * time.Duration(timeout)):
		return nil, errs.New("timeout occurred while gathering data")
	}
}

func (t *exporterTask) perform(s Scheduler) {
	// pass item key as parameter so it can be safely updated while task is being processed in its goroutine
	go func(itemkey string) {
		var result *plugin.Result
		now := time.Now()
		var key string
		var params []string
		var err error

		if key, params, err = itemutil.ParseKey(itemkey); err == nil {
			var ret interface{}

			ret, err = invokeExport(t.plugin.impl, key, params, t)

			if err == nil {
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

func (t *exporterTask) Timeout() int {
	return t.item.timeout
}

func (t *exporterTask) Delay() string {
	return t.item.delay
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

func (t *directExporterTask) invokeExport(key string, params []string) (any, error) {
	ret, err := invokeExport(t.plugin.impl, key, params, t)

	if err != nil {
		log.Debugf("failed to execute direct exporter task for key '%s[%s]' error: '%s'", key, params, err.Error())

		return nil, err
	}

	log.Debugf("executed direct exporter task for key '%s[%s]'", key, params)
	if ret != nil {
		rt := reflect.TypeOf(ret)
		switch rt.Kind() {
		case reflect.Slice, reflect.Array:
			return nil, errors.New("Multiple return values are not supported for single passive checks")
		default:
			return ret, nil
		}
	}

	return ret, nil
}

func (t *directExporterTask) perform(s Scheduler) {
	// pass item key as parameter so it can be safely updated while task is being processed in its goroutine
	go func(itemkey string) {
		var result *plugin.Result
		now := time.Now()
		var key string
		var params []string
		var err error

		if now.After(t.expire) {
			err = errors.New("Timeout while waiting for item in queue.")
			log.Debugf("direct exporter task expired for key '%s' error: '%s'", itemkey, err.Error())
		} else {
			if key, params, err = itemutil.ParseKey(itemkey); err == nil {
				var ret interface{}
				log.Debugf("executing direct exporter task for key '%s'", itemkey)

				ret, err = t.invokeExport(key, params)
				if err == nil {
					result = itemutil.ValueToResult(t.item.itemid, now, ret)
					t.output.Write(result)
					t.done = true
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

func (t *directExporterTask) Timeout() int {
	return t.item.timeout
}

func (t *directExporterTask) Delay() string {
	return t.item.delay
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
	items  []*plugin.Item
	client ClientAccessor
}

func (t *watcherTask) perform(s Scheduler) {
	log.Debugf("plugin %s: executing watcher task", t.plugin.name())
	go func() {
		watcher, _ := t.plugin.impl.(plugin.Watcher)
		watcher.Watch(t.items, t)
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

func (t *watcherTask) Timeout() int {
	return 0
}

func (t *watcherTask) Delay() string {
	return ""
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

// commandTask executes remote commands received with active requests
type commandTask struct {
	taskBase
	id      uint64
	params  []string
	output  resultcache.Writer
	timeout int
}

func (t *commandTask) ClientID() (clientid uint64) {
	return agent.MaxBuiltinClientID
}

func (t *commandTask) Output() (output plugin.ResultWriter) {
	return nil
}

func (t *commandTask) ItemID() (itemid uint64) {
	return 0
}

func (t *commandTask) Meta() (meta *plugin.Meta) {
	return nil
}

func (t *commandTask) GlobalRegexp() plugin.RegexpMatcher {
	return nil
}

func (t *commandTask) Timeout() int {
	return t.timeout
}

func (t *commandTask) Delay() string {
	return ""
}

func (t *commandTask) isRecurring() bool {
	return false
}

func (t *commandTask) perform(s Scheduler) {
	// execute remote command
	go func() {
		e := t.plugin.impl.(plugin.Exporter)

		var cr *resultcache.CommandResult

		if ret, err := e.Export("system.run", t.params, t); err == nil {
			if ret != nil {
				cr = &resultcache.CommandResult{
					ID:     t.id,
					Result: *itemutil.ValueToString(ret),
				}
			}
		} else {
			log.Debugf("failed to execute remote command '%s' error: '%s'",
				t.params[0], err.Error())

			cr = &resultcache.CommandResult{
				ID:    t.id,
				Error: err,
			}
		}

		t.output.WriteCommand(cr)
		t.output.Flush()

		s.FinishTask(t)
	}()
}

func (t *commandTask) reschedule(now time.Time) (err error) {
	t.scheduled = time.Unix(now.Unix(), priorityCommandTaskNs)

	return
}
