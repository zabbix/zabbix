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
	"errors"
	"fmt"
	"math"
	"sort"
	"time"

	"git.zabbix.com/ap/plugin-support/conf"
	"git.zabbix.com/ap/plugin-support/log"
	"git.zabbix.com/ap/plugin-support/plugin"
	"git.zabbix.com/ap/plugin-support/plugin/comms"
	"zabbix.com/internal/agent"
	"zabbix.com/internal/agent/alias"
	"zabbix.com/internal/agent/keyaccess"
	"zabbix.com/internal/monitor"
	"zabbix.com/pkg/glexpr"
	"zabbix.com/pkg/itemutil"
	"zabbix.com/plugins/external"
)

const (
	// number of seconds to wait for plugins to finish during scheduler shutdown
	shutdownTimeout = 5
	// inactive shutdown value
	shutdownInactive = -1
)

// Manager implements Scheduler interface and manages plugin interface usage.
type Manager struct {
	input       chan interface{}
	plugins     map[string]*pluginAgent
	pluginQueue pluginHeap
	clients     map[uint64]*client
	aliases     *alias.Manager
	// number of active tasks (running in their own goroutines)
	activeTasksNum int
	// number of seconds left on shutdown timer
	shutdownSeconds int
}

// updateRequest contains list of metrics monitored by a client and additional client configuration data.
type updateRequest struct {
	clientID                   uint64
	sink                       plugin.ResultWriter
	firstActiveChecksRefreshed bool
	requests                   []*plugin.Request
	expressions                []*glexpr.Expression
}

// queryRequest contains status/debug query request.
type queryRequest struct {
	command string
	sink    chan string
}

// queryRequestUserParams contains status user parameters query request.
type queryRequestUserParams struct {
	sink chan string
}

type Scheduler interface {
	UpdateTasks(clientID uint64, writer plugin.ResultWriter, firstActiveChecksRefreshed bool,
		expressions []*glexpr.Expression, requests []*plugin.Request)
	FinishTask(task performer)
	PerformTask(key string, timeout time.Duration, clientID uint64) (result string, err error)
	Query(command string) (status string)
	QueryUserParams() (status string)
}

// cleanupClient performs deactivation of plugins the client is not using anymore.
// It's called after client update and once per hour for the client associated to
// single passive checks.
func (m *Manager) cleanupClient(c *client, now time.Time) {
	// get a list of plugins the client stopped using
	released := c.cleanup(m.plugins, now)
	for _, p := range released {
		// check if the plugin is used by other clients
		if p.refcount != 0 {
			continue
		}
		log.Debugf("[%d] deactivate unused plugin %s", c.id, p.name())

		// deactivate recurring tasks
		for deactivate := true; deactivate; {
			deactivate = false
			for _, t := range p.tasks {
				if t.isActive() && t.isRecurring() {
					t.deactivate()
					// deactivation can change tasks ordering, so repeat the iteration if task was deactivated
					deactivate = true
					break
				}
			}
		}

		// queue stopper task if plugin has Runner interface
		if _, ok := p.impl.(plugin.Runner); ok {
			task := &stopperTask{
				taskBase: taskBase{plugin: p, active: true},
			}
			if err := task.reschedule(now); err != nil {
				log.Debugf("[%d] cannot schedule stopper task for plugin %s", c.id, p.name())
				continue
			}
			p.enqueueTask(task)
			log.Debugf("[%d] created stopper task for plugin %s", c.id, p.name())

			if p.queued() {
				m.pluginQueue.Update(p)
			}
		}

		// queue plugin if there are still some tasks left to be finished before deactivating
		if len(p.tasks) != 0 {
			if !p.queued() {
				heap.Push(&m.pluginQueue, p)
			}
		}
	}
}

// processUpdateRequest processes client update request. It's being used for multiple requests
// (active checks on a server) and also for direct requets (single passive and internal checks).
func (m *Manager) processUpdateRequest(update *updateRequest, now time.Time) {
	log.Debugf("[%d] processing update request (%d requests)", update.clientID, len(update.requests))

	// immediately fail direct checks and ignore bulk requests when shutting down
	if m.shutdownSeconds != shutdownInactive {
		if update.clientID <= agent.MaxBuiltinClientID {
			if len(update.requests) == 1 {
				update.sink.Write(&plugin.Result{
					Itemid: update.requests[0].Itemid,
					Error:  errors.New("Cannot obtain item value during shutdown process."),
					Ts:     now,
				})
			} else {
				log.Warningf("[%d] direct checks can contain only single request while received %d requests",
					update.clientID, len(update.requests))
			}
		}
		return
	}

	var c *client
	var ok bool
	if c, ok = m.clients[update.clientID]; !ok {
		if len(update.requests) == 0 {
			log.Debugf("[%d] skipping empty update for unregistered client", update.clientID)
			return
		}
		log.Debugf("[%d] registering new client", update.clientID)
		c = newClient(update.clientID, update.sink)
		m.clients[update.clientID] = c
	}

	c.updateExpressions(update.expressions)

	for _, r := range update.requests {
		var key string
		var params []string
		var err error
		var p *pluginAgent

		r.Key = m.aliases.Get(r.Key)
		if key, params, err = itemutil.ParseKey(r.Key); err == nil {
			p, ok = m.plugins[key]
			if ok && update.clientID != agent.LocalChecksClientID {
				ok = keyaccess.CheckRules(key, params)
			}
			if !ok {
				err = fmt.Errorf("Unknown metric %s", key)
			} else {
				err = c.addRequest(p, r, update.sink, now, update.firstActiveChecksRefreshed)
			}
		}

		if err != nil {
			if c.id > agent.MaxBuiltinClientID {
				if tacc, ok := c.exporters[r.Itemid]; ok {
					log.Debugf("deactivate exporter task for item %d because of error: %s", r.Itemid, err)
					tacc.task().deactivate()
				}
			}
			update.sink.Write(&plugin.Result{Itemid: r.Itemid, Error: err, Ts: now})
			log.Debugf("[%d] cannot monitor metric \"%s\": %s", update.clientID, r.Key, err.Error())
			continue
		}

		if !p.queued() {
			heap.Push(&m.pluginQueue, p)
		} else {
			m.pluginQueue.Update(p)
		}
	}

	m.cleanupClient(c, now)
}

// processQueue processes queued plugins/tasks
func (m *Manager) processQueue(now time.Time) {
	seconds := now.Unix()
	for p := m.pluginQueue.Peek(); p != nil; p = m.pluginQueue.Peek() {
		if task := p.peekTask(); task != nil {
			if task.getScheduled().Unix() > seconds {
				break
			}

			heap.Pop(&m.pluginQueue)
			if !p.hasCapacity() {
				// plugin has no free capacity for the next task, keep the plugin out of queue
				// until active tasks finishes and the required capacity is released
				continue
			}

			// take the task out of plugin tasks queue and perform it
			m.activeTasksNum++
			p.reserveCapacity(p.popTask())
			task.perform(m)

			// if the plugin has capacity for the next task put it back into plugin queue
			if !p.hasCapacity() {
				continue
			}
			heap.Push(&m.pluginQueue, p)
		} else {
			// plugins with empty task queue should not be in Manager queue
			heap.Pop(&m.pluginQueue)
		}
	}
}

// processAndFlushUserParamQueue processes queued user parameters plugins/tasks and/or removes them
func (m *Manager) processAndFlushUserParamQueue(now time.Time) {
	seconds := now.Unix()
	num := m.pluginQueue.Len()
	var pluginsBuf []*pluginAgent

	for p := m.pluginQueue.Peek(); p != nil && num > 0; p = m.pluginQueue.Peek() {
		heap.Pop(&m.pluginQueue)
		num--

		if !p.usrprm {
			pluginsBuf = append(pluginsBuf, p)
			continue
		}

		if task := p.peekTask(); task != nil {
			if !p.hasCapacity() || task.getScheduled().Unix() > seconds {
				continue
			}

			m.activeTasksNum++
			p.reserveCapacity(p.popTask())
			task.perform(m)
		}
	}

	for _, p := range pluginsBuf {
		m.pluginQueue.Push(p)
	}
}

// processFinishRequest handles finished tasks
func (m *Manager) processFinishRequest(task performer) {
	m.activeTasksNum--
	p := task.getPlugin()
	p.releaseCapacity(task)
	if p.active() && task.isActive() && task.isRecurring() {
		if err := task.reschedule(time.Now()); err != nil {
			log.Warningf("cannot reschedule plugin %s: %s", p.impl.Name(), err)
		} else {
			p.enqueueTask(task)
		}
	}
	if !p.queued() && p.hasCapacity() {
		heap.Push(&m.pluginQueue, p)
	}
}

// rescheduleQueue reschedules all queued tasks. This is done whenever time
// difference between ticks exceeds limits (for example during daylight saving changes).
func (m *Manager) rescheduleQueue(now time.Time) {
	// easier to rebuild queues than update each element
	queue := make(pluginHeap, 0, len(m.pluginQueue))
	for _, p := range m.pluginQueue {
		tasks := p.tasks
		p.tasks = make(performerHeap, 0, len(tasks))
		for _, t := range tasks {
			if err := t.reschedule(now); err == nil {
				p.enqueueTask(t)
			}
		}
		heap.Push(&queue, p)
	}
	m.pluginQueue = queue
}

// deactivatePlugins removes all tasks and creates stopper tasks for active runner plugins
func (m *Manager) deactivatePlugins() {
	m.shutdownSeconds = shutdownTimeout

	m.pluginQueue = make(pluginHeap, 0, len(m.pluginQueue))
	for _, p := range m.plugins {
		if p.refcount != 0 {
			p.tasks = make(performerHeap, 0)
			if _, ok := p.impl.(plugin.Runner); ok {
				task := &stopperTask{
					taskBase: taskBase{plugin: p, active: true},
				}
				p.enqueueTask(task)
				heap.Push(&m.pluginQueue, p)
				p.refcount = 0
				log.Debugf("created final stopper task for plugin %s", p.name())
			}
			p.refcount = 0
		}
	}
}

// run() is the main worker loop running in own goroutine until stopped
func (m *Manager) run() {
	defer log.PanicHook()
	log.Debugf("starting manager")
	// Adjust ticker creation at the 0 nanosecond timestamp. In reality it will have at least
	// some microseconds, which will be enough to include all scheduled tasks at this second
	// even with nanosecond priority adjustment.
	lastTick := time.Now()
	cleaned := lastTick
	time.Sleep(time.Duration(1e9 - lastTick.Nanosecond()))
	ticker := time.NewTicker(time.Second)
run:
	for {
		select {
		case <-ticker.C:
			now := time.Now()
			diff := now.Sub(lastTick)
			interval := time.Second * 10
			if diff <= -interval || diff >= interval {
				log.Warningf("detected %d time difference between queue checks, rescheduling tasks",
					int(math.Abs(float64(diff))/1e9))
				m.rescheduleQueue(now)
			}
			lastTick = now
			m.processQueue(now)
			if m.shutdownSeconds != shutdownInactive {
				m.shutdownSeconds--
				if m.shutdownSeconds == 0 {
					break run
				}
			} else {
				// cleanup plugins used by passive checks
				if now.Sub(cleaned) >= time.Hour {
					if passive, ok := m.clients[0]; ok {
						m.cleanupClient(passive, now)
					}
					// remove inactive clients
					for _, client := range m.clients {
						if len(client.pluginsInfo) == 0 {
							delete(m.clients, client.ID())
						}
					}
					cleaned = now
				}
			}
		case u := <-m.input:
			if u == nil {
				m.deactivatePlugins()
				if m.activeTasksNum+len(m.pluginQueue) == 0 {
					break run
				}
				m.processQueue(time.Now())
			}
			switch v := u.(type) {
			case *updateRequest:
				m.processUpdateRequest(v, time.Now())
				m.processQueue(time.Now())
			case performer:
				m.processFinishRequest(v)
				if m.shutdownSeconds != shutdownInactive && m.activeTasksNum+len(m.pluginQueue) == 0 {
					break run
				}
				m.processQueue(time.Now())
			case *queryRequest:
				if response, err := m.processQuery(v); err != nil {
					v.sink <- "cannot process request: " + err.Error()
				} else {
					v.sink <- response
				}
			case *queryRequestUserParams:
				var keys []string
				var rerr error

				metrics := plugin.ClearUserParamMetrics()

				if keys, rerr = agent.InitUserParameterPlugin(agent.Options.UserParameter,
					agent.Options.UnsafeUserParameters, agent.Options.UserParameterDir); rerr != nil {
					plugin.RestoreUserParamMetrics(metrics)
					v.sink <- "cannot process user parameters request: " + rerr.Error()
					continue
				}

				m.processAndFlushUserParamQueue(time.Now())

				tasks := make(map[string]performerHeap)

				for key, plg := range m.plugins {
					if plg.usrprm {
						tasks[key] = plg.tasks
						delete(m.plugins, key)
					}
				}

				for _, key := range keys {
					m.addUserParamsPlugin(key)
					m.plugins[key].refcount++
				}

				for pluginkey, ltasks := range tasks {
					for task := peekTask(ltasks); task != nil; task = peekTask(ltasks) {
						heap.Pop(&ltasks)

						for _, key := range keys {
							if task.isItemKeyEqual(key) {
								task.setPlugin(m.plugins[pluginkey])
								m.plugins[pluginkey].enqueueTask(task)
							}
						}
					}
				}

				for _, key := range keys {
					heap.Push(&m.pluginQueue, m.plugins[key])
				}

				v.sink <- "ok"
			}
		}
	}
	log.Debugf("manager has been stopped")
	monitor.Unregister(monitor.Scheduler)
}

type pluginOptions struct {
	Capacity int `conf:"optional"`
	System   struct {
		ForceActiveChecksOnStart *int `conf:"optional"`
		Capacity                 int  `conf:"optional"`
	} `conf:"optional"`
}

func (m *Manager) init() {
	m.input = make(chan interface{}, 10)
	m.pluginQueue = make(pluginHeap, 0, len(plugin.Metrics))
	m.clients = make(map[uint64]*client)
	m.plugins = make(map[string]*pluginAgent)
	m.shutdownSeconds = shutdownInactive

	metrics := make([]*plugin.Metric, 0, len(plugin.Metrics))

	for _, metric := range plugin.Metrics {
		metrics = append(metrics, metric)
	}
	sort.Slice(metrics, func(i, j int) bool {
		return metrics[i].Plugin.Name() < metrics[j].Plugin.Name()
	})

	pagent := &pluginAgent{}
	for _, metric := range metrics {
		if metric.Plugin != pagent.impl {
			capacity, forceActiveChecksOnStart := getPluginOptions(agent.Options.Plugins[metric.Plugin.Name()], metric.Plugin.Name())
			if capacity > metric.Plugin.Capacity() {
				log.Warningf("lowering the plugin %s capacity to %d as the configured capacity %d exceeds limits",
					metric.Plugin.Name(), metric.Plugin.Capacity(), capacity)
				capacity = metric.Plugin.Capacity()
			}

			pagent = &pluginAgent{
				impl:                     metric.Plugin,
				tasks:                    make(performerHeap, 0),
				maxCapacity:              capacity,
				usedCapacity:             0,
				forceActiveChecksOnStart: forceActiveChecksOnStart,
				index:                    -1,
				refcount:                 0,
				usrprm:                   metric.UsrPrm,
			}

			interfaces := ""
			if _, ok := metric.Plugin.(plugin.Exporter); ok {
				interfaces += "exporter, "
			}
			if _, ok := metric.Plugin.(plugin.Collector); ok {
				interfaces += "collector, "
			}
			if _, ok := metric.Plugin.(plugin.Runner); ok {
				interfaces += "runner, "
			}
			if _, ok := metric.Plugin.(plugin.Watcher); ok {
				interfaces += "watcher, "
			}
			if _, ok := metric.Plugin.(plugin.Configurator); ok {
				interfaces += "configurator, "
			}
			interfaces = interfaces[:len(interfaces)-2]

			if metric.Plugin.IsExternal() {
				ext := metric.Plugin.(*external.Plugin)
				metric.Plugin.SetCapacity(1)
				log.Infof("using plugin '%s' (%s) providing following interfaces: %s", metric.Plugin.Name(),
					ext.Path, interfaces)
			} else {
				log.Infof("using plugin '%s' (built-in) providing following interfaces: %s", metric.Plugin.Name(),
					interfaces)
			}
		}
		m.plugins[metric.Key] = pagent
	}
}

func (m *Manager) Start() {
	log.Infof("%s", comms.GetPluginVersionMessage())

	monitor.Register(monitor.Scheduler)
	go m.run()
}

func (m *Manager) Stop() {
	m.input <- nil
}

func (m *Manager) UpdateTasks(clientID uint64, writer plugin.ResultWriter, firstActiveChecksRefreshed bool,
	expressions []*glexpr.Expression, requests []*plugin.Request) {

	m.input <- &updateRequest{clientID: clientID,
		sink:                       writer,
		requests:                   requests,
		expressions:                expressions,
		firstActiveChecksRefreshed: firstActiveChecksRefreshed,
	}
}

type resultWriter chan *plugin.Result

func (r resultWriter) Write(result *plugin.Result) {
	r <- result
}

func (r resultWriter) Flush() {
}

func (r resultWriter) SlotsAvailable() int {
	return 1
}

func (r resultWriter) PersistSlotsAvailable() int {
	return 1
}

func (m *Manager) PerformTask(key string, timeout time.Duration, clientID uint64) (result string, err error) {
	var lastLogsize uint64
	var mtime int

	w := make(resultWriter, 1)

	m.UpdateTasks(clientID, w, false, nil, []*plugin.Request{{Key: key, LastLogsize: &lastLogsize, Mtime: &mtime}})

	select {
	case r := <-w:
		if r.Error == nil {
			if r.Value != nil {
				result = *r.Value
			} else {
				// single metric requests do not support empty values, return error instead
				err = errors.New("No values have been gathered yet.")
			}
		} else {
			err = r.Error
		}
	case <-time.After(timeout):
		err = fmt.Errorf("Timeout occurred while gathering data.")
	}
	return
}

func (m *Manager) FinishTask(task performer) {
	m.input <- task
}

func (m *Manager) Query(command string) (status string) {
	request := &queryRequest{command: command, sink: make(chan string)}
	m.input <- request
	return <-request.sink
}

func (m *Manager) QueryUserParams() (status string) {
	request := &queryRequestUserParams{sink: make(chan string)}
	m.input <- request
	return <-request.sink
}

func (m *Manager) validatePlugins(options *agent.AgentOptions) (err error) {
	for _, p := range plugin.Plugins {
		if c, ok := p.(plugin.Configurator); ok && !p.IsExternal() {
			if err = c.Validate(options.Plugins[p.Name()]); err != nil {
				return fmt.Errorf("invalid plugin %s configuration: %s", p.Name(), err)
			}
		}
	}
	return
}

func (m *Manager) configure(options *agent.AgentOptions) (err error) {
	m.aliases, err = alias.NewManager(options)
	return
}

func NewManager(options *agent.AgentOptions) (mannager *Manager, err error) {
	var m Manager
	m.init()
	if err = m.validatePlugins(options); err != nil {
		return
	}
	return &m, m.configure(options)
}

func (m *Manager) addUserParamsPlugin(key string) {
	var metric *plugin.Metric

	for _, metric = range plugin.Metrics {
		if metric.Key == key {
			break
		}
	}

	capacity := metric.Plugin.Capacity()

	pagent := &pluginAgent{
		impl:         metric.Plugin,
		tasks:        make(performerHeap, 0),
		maxCapacity:  capacity,
		usedCapacity: 0,
		index:        -1,
		refcount:     0,
		usrprm:       metric.UsrPrm,
	}

	m.plugins[key] = pagent
}

func peekTask(tasks performerHeap) performer {
	if len(tasks) == 0 {
		return nil
	}

	return tasks[0]
}

func getPluginOptions(optsRaw interface{}, name string) (capacity int, forceActiveChecksOnStart int) {
	pluginCap, pluginSystemCap, pluginForceActiveChecksOnStart := getPluginOpts(optsRaw, name)

	if pluginSystemCap > 0 {
		if pluginCap > 0 {
			log.Warningf("both Plugins.%s.Capacity and Plugins.%s.System.Capacity configuration parameters are set, using System.Capacity: %d",
				name, name, pluginSystemCap)
		}
		capacity = pluginSystemCap
	} else if pluginCap > 0 {
		log.Warningf(
			"plugin %s configuration parameter Plugins.%s.Capacity is deprecated, use Plugins.%s.System.Capacity instead",
			name, name, name,
		)
		capacity = pluginCap
	} else {
		capacity = plugin.DefaultCapacity
	}

	if nil != pluginForceActiveChecksOnStart {
		if *pluginForceActiveChecksOnStart > 1 || *pluginForceActiveChecksOnStart < 0 {
			log.Warningf("invalid Plugins.%s.System.ForceActiveChecksOnStart configuration parameter: %d",
				name, *pluginForceActiveChecksOnStart)
			forceActiveChecksOnStart = agent.Options.ForceActiveChecksOnStart
		} else {
			forceActiveChecksOnStart = *pluginForceActiveChecksOnStart
		}
	} else {
		forceActiveChecksOnStart = agent.Options.ForceActiveChecksOnStart
	}

	return
}

func getPluginOpts(optsRaw interface{}, name string) (pluginCap, pluginSystemCap int, forceActiveChecksOnStart *int) {
	var opt pluginOptions

	if optsRaw == nil {
		return
	}

	if err := conf.Unmarshal(optsRaw, &opt, false); err != nil {
		log.Warningf("invalid plugin %s configuration: %s", name, err)

		return
	}

	pluginCap = opt.Capacity
	pluginSystemCap = opt.System.Capacity
	forceActiveChecksOnStart = opt.System.ForceActiveChecksOnStart

	return
}
