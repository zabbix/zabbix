/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

type clientItem struct {
	itemid uint64
	delay  string
	key    string
}

type pluginInfo struct {
	used    time.Time
	watcher *watcherTask
}

type client struct {
	id                 uint64
	exporters          map[uint64]exporterTaskAccessor
	pluginsInfo        map[*pluginAgent]*pluginInfo
	refreshUnsupported int
	globalRegexp       unsafe.Pointer
	output             plugin.ResultWriter
}

type ClientAccessor interface {
	RefreshUnsupported() int
	Output() plugin.ResultWriter
	GlobalRegexp() *glexpr.Bundle
	ID() uint64
}

// RefreshUnsupported is used only by scheduler, no synchronization is required
func (c *client) RefreshUnsupported() int {
	return c.refreshUnsupported
}

// GlobalRegexp() is used by tasks to implement ContextProvider interface.
// In theory it can be accessed by plugins and replaced by scheduler at the same time,
// so pointer access must be synchronized. The global regexp contents are never changed,
// only replaced, so pointer synchronization is enough.
func (c *client) GlobalRegexp() *glexpr.Bundle {
	return (*glexpr.Bundle)(atomic.LoadPointer(&c.globalRegexp))
}

// While ID() is used by tasks to implement ContextProvider interface, client ID cannot
// change, so no synchronization is required.
func (c *client) ID() uint64 {
	return c.id
}

// While Output() is used by tasks to implement ContextProvider interface, client output cannot
// change, so no synchronization is required.
func (c *client) Output() plugin.ResultWriter {
	return c.output
}

func (c *client) addRequest(p *pluginAgent, r *plugin.Request, sink plugin.ResultWriter, now time.Time) (err error) {
	var info *pluginInfo
	var ok bool

	log.Debugf("[%d] adding new request for key: '%s'", c.id, r.Key)

	if info, ok = c.pluginsInfo[p]; !ok {
		info = &pluginInfo{}
	}

	tasks := make([]performer, 0, 6)

	// handle Collector interface
	if col, ok := p.impl.(plugin.Collector); ok {
		if p.refcount == 0 {
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

		if c.id != 0 {
			var task *exporterTask
			if _, err = zbxlib.GetNextcheck(r.Itemid, r.Delay, now, false, c.refreshUnsupported); err != nil {
				return err
			}
			if tacc, ok = c.exporters[r.Itemid]; ok {
				task = tacc.task()
				if task.updated.Equal(now) {
					return errors.New("duplicate itemid found")
				}
				if task.plugin != p {
					// create new task if item key has been changed and now is handled by other plugin
					task.deactivate()
					ok = false
				}
			}

			if !ok {
				task = &exporterTask{
					taskBase: taskBase{plugin: p, active: true, recurring: true},
					item:     clientItem{itemid: r.Itemid, delay: r.Delay, key: r.Key},
					updated:  now,
					client:   c,
					output:   sink,
				}
				if err = task.reschedule(now); err != nil {
					return
				}
				c.exporters[r.Itemid] = task
				tasks = append(tasks, task)
				log.Debugf("[%d] created exporter task for plugin '%s' itemid:%d key '%s'",
					c.id, p.name(), task.item.itemid, task.item.key)
			} else {
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
			task := &directExporterTask{
				taskBase: taskBase{plugin: p, active: true, recurring: true},
				item:     clientItem{itemid: r.Itemid, delay: r.Delay, key: r.Key},
				expire:   now.Add(time.Duration(agent.Options.Timeout) * time.Second),
				client:   c,
				output:   sink,
			}
			// cache scheduled (non direct) request tasks
			if err = task.reschedule(now); err != nil {
				return
			}
			tasks = append(tasks, task)
			log.Debugf("[%d] created direct exporter task for plugin '%s' itemid:%d key '%s'",
				c.id, p.name(), task.item.itemid, task.item.key)
		}
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

	// Watcher plugins are not supported by direct requests
	if c.id != 0 {
		// handle Watcher interface
		if _, ok := p.impl.(plugin.Watcher); ok {
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

	if info.used.IsZero() {
		p.refcount++
		c.pluginsInfo[p] = info
	}
	info.used = now

	return nil
}

func (c *client) cleanup(plugins map[string]*pluginAgent, now time.Time) (released []*pluginAgent) {
	released = make([]*pluginAgent, 0, len(c.pluginsInfo))
	// remover references to temporary watcher tasks
	for _, p := range c.pluginsInfo {
		p.watcher = nil
	}

	// remove unused items
	for _, tacc := range c.exporters {
		task := tacc.task()
		if task.updated.Before(now) {
			delete(c.exporters, task.item.itemid)
			log.Debugf("[%d] released unused exporter for itemid:%d", c.id, task.item.itemid)
			task.deactivate()
		}
	}

	var expiry time.Time
	// Direct requests are handled by special client with id 0. Such requests have
	// day+hour (to keep once per day checks without expiring) expiry time before
	// used plugins are released.
	if c.id != 0 {
		expiry = now
	} else {
		expiry = now.Add(-time.Hour * 25)
	}

	// deactivate plugins
	for _, p := range plugins {
		if info, ok := c.pluginsInfo[p]; ok {
			if info.used.Before(expiry) {
				// perform empty watch task before closing
				if c.id != 0 {
					if _, ok := p.impl.(plugin.Watcher); ok {
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
				}

				released = append(released, p)
				delete(c.pluginsInfo, p)
				p.refcount--
				// TODO: define uniform time format
				if c.id != 0 {
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

func newClient(id uint64, output plugin.ResultWriter) (b *client) {
	b = &client{
		id:          id,
		exporters:   make(map[uint64]exporterTaskAccessor),
		pluginsInfo: make(map[*pluginAgent]*pluginInfo),
		output:      output,
	}

	return
}
