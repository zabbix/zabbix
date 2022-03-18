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
	"hash/fnv"
	"sync/atomic"
	"time"
	"unsafe"

	"zabbix.com/internal/agent"
	"zabbix.com/pkg/glexpr"
	"zabbix.com/pkg/log"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/zbxlib"
)

// clientItem represents item monitored by client
type clientItem struct {
	itemid uint64
	delay  string
	key    string
}

// pluginInfo is used to track plugin usage by client
type pluginInfo struct {
	used time.Time
	// temporary link to a watcherTask during update
	watcher *watcherTask
}

// client represents source of items (metrics) to be queried.
// Each server for active checks is represented by a separate client.
// There is a predefined clients to handle:
//    all single passive checks (client id 1)
//    all internal checks (resolving HostnameItem, HostMetadataItem, HostInterfaceItem) (client id 0)
type client struct {
	// Client id. Predefined clients have ids < 100, while clients active checks servers (ServerActive)
	// have auto incrementing id starting with 100.
	id uint64
	// A map of itemids to the associated exporter tasks. It's used to update task when item parameters change.
	exporters map[uint64]exporterTaskAccessor
	// plugins used by client
	pluginsInfo map[*pluginAgent]*pluginInfo
	// server global regular expression bundle
	globalRegexp unsafe.Pointer
	// plugin result sink, can be nil for bulk passive checks (in future)
	output plugin.ResultWriter
}

// ClientAccessor interface exports client data required for scheduler tasks.
type ClientAccessor interface {
	Output() plugin.ResultWriter
	GlobalRegexp() *glexpr.Bundle
	ID() uint64
}

// GlobalRegexp returns global regular expression bundle.
// This function is used by tasks to implement ContextProvider interface.
// It can be accessed by plugins and replaced by scheduler at the same time,
// so pointer access must be synchronized. The global regexp contents are never changed,
// only replaced, so pointer synchronization is enough.
func (c *client) GlobalRegexp() *glexpr.Bundle {
	return (*glexpr.Bundle)(atomic.LoadPointer(&c.globalRegexp))
}

// ID returns client id.
// While it's used by tasks to implement ContextProvider interface, client ID cannot
// change, so no synchronization is required.
func (c *client) ID() uint64 {
	return c.id
}

// Output returns client output interface where plugins results can be written.
// While it's used by tasks to implement ContextProvider interface, client output cannot
// change, so no synchronization is required.
func (c *client) Output() plugin.ResultWriter {
	return c.output
}

// addRequest requests client to start monitoring/update item described by request 'r' using plugin 'p' (*pluginAgent)
// with output writer 'sink'
func (c *client) addRequest(p *pluginAgent, r *plugin.Request, sink plugin.ResultWriter, now time.Time,
	firstActiveChecksRefreshed bool) (err error) {
	var info *pluginInfo
	var ok bool

	log.Debugf("[%d] adding new request for key: '%s'", c.id, r.Key)

	if info, ok = c.pluginsInfo[p]; !ok {
		info = &pluginInfo{}
	}

	// list of created tasks to be queued
	tasks := make([]performer, 0, 6)

	// handle Collector interface
	if col, ok := p.impl.(plugin.Collector); ok {
		if p.refcount == 0 {
			// calculate collector seed to avoid scheduling all collectors at the same time
			h := fnv.New32a()
			_, _ = h.Write([]byte(p.impl.Name()))
			task := &collectorTask{
				taskBase: taskBase{plugin: p, active: true, recurring: true},
				seed:     uint64(h.Sum32())}
			if err = task.reschedule(now); err != nil {
				return
			}
			tasks = append(tasks, task)
			log.Debugf("[%d] created collector task for plugin %s with collecting interval %d", c.id, p.name(),
				col.Period())
		}
	}

	// handle Exporter interface
	if _, ok := p.impl.(plugin.Exporter); ok {
		var tacc exporterTaskAccessor

		if c.id > agent.MaxBuiltinClientID {
			var task *exporterTask
			var scheduling bool

			if _, scheduling, err = zbxlib.GetNextcheck(r.Itemid, r.Delay, now); err != nil {
				return err
			}
			if tacc, ok = c.exporters[r.Itemid]; ok {
				task = tacc.task()
				if task.updated.Equal(now) {
					return errors.New("duplicate itemid found")
				}
				if task.plugin != p {
					// decativate current exporter task and create new one if the item key has been changed
					// and the new metric is handled by other plugin
					task.deactivate()
					ok = false
				}
			}

			if !ok {
				// create and register new exporter task
				task = &exporterTask{
					taskBase: taskBase{plugin: p, active: true, recurring: true},
					item:     clientItem{itemid: r.Itemid, delay: r.Delay, key: r.Key},
					updated:  now,
					client:   c,
					output:   sink,
				}

				if scheduling == false && firstActiveChecksRefreshed == false && p.forceActiveChecksOnStart != 0 {
					task.scheduled = time.Unix(now.Unix(), priorityExporterTaskNs)
				} else if err = task.reschedule(now); err != nil {
					return
				}
				c.exporters[r.Itemid] = task
				tasks = append(tasks, task)
				log.Debugf("[%d] created exporter task for plugin '%s' itemid:%d key '%s'",
					c.id, p.name(), task.item.itemid, task.item.key)
			} else {
				// update existing exporter task
				task = tacc.task()
				task.updated = now
				task.item.key = r.Key
				if task.item.delay != r.Delay {
					task.item.delay = r.Delay
					if err = task.reschedule(now); err != nil {
						return
					}
					p.tasks.Update(task)
					log.Debugf("[%d] updated exporter task for plugin '%s' itemid:%d key '%s'",
						c.id, p.name(), task.item.itemid, task.item.key)
				}
			}
			task.meta.SetLastLogsize(*r.LastLogsize)
			task.meta.SetMtime(int32(*r.Mtime))

		} else {
			// handle single passive check or internal request
			task := &directExporterTask{
				taskBase: taskBase{plugin: p, active: true, recurring: true},
				item:     clientItem{itemid: r.Itemid, delay: r.Delay, key: r.Key},
				expire:   now.Add(time.Duration(agent.Options.Timeout) * time.Second),
				client:   c,
				output:   sink,
			}
			if err = task.reschedule(now); err != nil {
				return
			}
			tasks = append(tasks, task)
			log.Debugf("[%d] created direct exporter task for plugin '%s' itemid:%d key '%s'",
				c.id, p.name(), task.item.itemid, task.item.key)
		}
	} else if c.id <= agent.MaxBuiltinClientID {
		return fmt.Errorf(`The "%s" key is not supported in test or single passive check mode`, r.Key)
	}

	// handle runner interface for inactive plugins
	if _, ok := p.impl.(plugin.Runner); ok {
		if p.refcount == 0 {
			task := &starterTask{
				taskBase: taskBase{plugin: p, active: true},
			}
			if err = task.reschedule(now); err != nil {
				return
			}
			tasks = append(tasks, task)
			log.Debugf("[%d] created starter task for plugin %s", c.id, p.name())
		}
	}

	// handle Watcher interface (not supported by single passive check or internal requests)
	if _, ok := p.impl.(plugin.Watcher); ok && c.id > agent.MaxBuiltinClientID {
		if info.watcher == nil {
			info.watcher = &watcherTask{
				taskBase: taskBase{plugin: p, active: true},
				requests: make([]*plugin.Request, 0, 1),
				client:   c,
			}
			if err = info.watcher.reschedule(now); err != nil {
				return
			}
			tasks = append(tasks, info.watcher)

			log.Debugf("[%d] created watcher task for plugin %s", c.id, p.name())
		}
		info.watcher.requests = append(info.watcher.requests, r)
	}

	// handle configurator interface for inactive plugins
	if _, ok := p.impl.(plugin.Configurator); ok {
		if p.refcount == 0 {
			task := &configuratorTask{
				taskBase: taskBase{plugin: p, active: true},
				options:  &agent.Options,
			}
			_ = task.reschedule(now)
			tasks = append(tasks, task)
			log.Debugf("[%d] created configurator task for plugin %s", c.id, p.name())
		}
	}

	for _, t := range tasks {
		p.enqueueTask(t)
	}

	// update plugin usage information
	if info.used.IsZero() {
		p.refcount++
		c.pluginsInfo[p] = info
	}
	info.used = now

	return nil
}

// cleanup releases unused uplugins. For external clients it's done after update,
// while for internal clients once per hour.
func (c *client) cleanup(plugins map[string]*pluginAgent, now time.Time) (released []*pluginAgent) {
	released = make([]*pluginAgent, 0, len(c.pluginsInfo))
	// remove reference to temporary watcher tasks
	for _, p := range c.pluginsInfo {
		p.watcher = nil
	}

	// unmap not monitored exporter tasks
	for _, tacc := range c.exporters {
		task := tacc.task()
		if task.updated.Before(now) {
			delete(c.exporters, task.item.itemid)
			log.Debugf("[%d] released unused exporter for itemid:%d", c.id, task.item.itemid)
			task.deactivate()
		}
	}

	var expiry time.Time
	// Direct requests are handled by special clients with id <= MaxBuiltinClientID.
	// Such requests have day+hour (to keep once per day checks without expiring)
	// expiry time before used plugins are released.
	if c.id > agent.MaxBuiltinClientID {
		expiry = now
	} else {
		expiry = now.Add(-time.Hour * 25)
	}

	// deactivate plugins
	for _, p := range plugins {
		if info, ok := c.pluginsInfo[p]; ok {
			if info.used.Before(expiry) {
				// perform empty watch task before releasing plugin, so it could
				// release internal resources allocated to monitor this client
				if _, ok := p.impl.(plugin.Watcher); ok && c.id > agent.MaxBuiltinClientID {
					task := &watcherTask{
						taskBase: taskBase{plugin: p, active: true},
						requests: make([]*plugin.Request, 0),
						client:   c,
					}
					if err := task.reschedule(now); err == nil {
						p.enqueueTask(task)
						log.Debugf("[%d] created watcher task for plugin %s", c.id, p.name())
					} else {
						// currently watcher rescheduling cannot fail, but log a warning for future
						log.Warningf("[%d] cannot reschedule plugin '%s' closing watcher task: %s",
							c.id, p.impl.Name(), err)
					}
				}

				// release plugin
				released = append(released, p)
				delete(c.pluginsInfo, p)
				p.refcount--
				// TODO: define uniform time format
				if c.id > agent.MaxBuiltinClientID {
					log.Debugf("[%d] released unused plugin %s", c.id, p.name())
				} else {
					log.Debugf("[%d] released plugin %s as not used since %s", c.id, p.name(),
						info.used.Format(time.Stamp))
				}
			}
		}
	}
	return
}

// updateExpressions updates server global regular expression bundle
func (c *client) updateExpressions(expressions []*glexpr.Expression) {
	// reset expressions if changed
	glexpr.SortExpressions(expressions)
	var grxp *glexpr.Bundle
	if c.globalRegexp != nil {
		grxp = (*glexpr.Bundle)(atomic.LoadPointer(&c.globalRegexp))
		if !grxp.CompareExpressions(expressions) {
			grxp = nil
		}
	}

	if grxp == nil {
		grxp = glexpr.NewBundle(expressions)
		atomic.StorePointer(&c.globalRegexp, unsafe.Pointer(grxp))
	}
}

// newClient creates new client
func newClient(id uint64, output plugin.ResultWriter) (b *client) {
	b = &client{
		id:          id,
		exporters:   make(map[uint64]exporterTaskAccessor),
		pluginsInfo: make(map[*pluginAgent]*pluginInfo),
		output:      output,
	}

	return
}
