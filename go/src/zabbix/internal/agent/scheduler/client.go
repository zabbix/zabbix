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
	"hash/fnv"
	"strings"
	"time"
	"zabbix/internal/agent"
	"zabbix/internal/plugin"
	"zabbix/pkg/itemutil"
	"zabbix/pkg/log"
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
	id        uint64
	exporters map[uint64]exporterTaskAccessor
	plugins   map[*pluginAgent]*pluginInfo
}

func (c *client) addRequest(p *pluginAgent, r *plugin.Request, sink plugin.ResultWriter, now time.Time) (err error) {
	var info *pluginInfo
	var ok bool
	if info, ok = c.plugins[p]; !ok {
		info = &pluginInfo{}
	}

	tasks := make([]performer, 0, 6)

	// handle Collector interface
	if col, ok := p.impl.(plugin.Collector); ok {
		if p.refcount == 0 {
			h := fnv.New32a()
			_, _ = h.Write([]byte(p.impl.Name()))
			task := &collectorTask{
				taskBase: taskBase{plugin: p, active: true, onetime: false},
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
		if r.Itemid != 0 {
			if _, err = itemutil.GetNextcheck(r.Itemid, r.Delay, false, now); err != nil {
				return err
			}
		}
		if tacc, ok := c.exporters[r.Itemid]; !ok {
			task := &exporterTask{
				taskBase: taskBase{plugin: p, active: true, onetime: r.Itemid == 0},
				writer:   sink,
				item:     clientItem{itemid: r.Itemid, delay: r.Delay, key: r.Key},
				updated:  now,
			}

			// cache scheduled (non direct) requests
			if r.Itemid != 0 {
				c.exporters[r.Itemid] = task
				if err = task.reschedule(now); err != nil {
					return
				}
			}
			tasks = append(tasks, task)
			log.Debugf("[%d] created exporter task for plugin %s", c.id, p.name())
		} else {
			tacc.setUpdated(now)
			item := tacc.getItem()
			item.key = r.Key
			if item.delay != r.Delay {
				item.delay = r.Delay
				if err = tacc.reschedule(now); err != nil {
					tacc.deactivate()
					return
				}
				p.tasks.Update(tacc)
				log.Debugf("[%d] updated exporter task for item %d %s", c.id, item.itemid, item.key)
			}
		}
	}

	// handle runner interface for inactive plugins
	if _, ok := p.impl.(plugin.Runner); ok {
		if p.refcount == 0 {
			task := &starterTask{
				taskBase: taskBase{plugin: p, active: true, onetime: true},
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
					taskBase: taskBase{plugin: p, active: true, onetime: true},
					sink:     sink,
					requests: make([]*plugin.Request, 0, 1),
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
	if _, ok := p.impl.(plugin.Configurator); ok && agent.Options.Plugins != nil {
		if p.refcount == 0 {
			if options, ok := agent.Options.Plugins[strings.Title(p.impl.Name())]; ok {
				task := &configerTask{
					taskBase: taskBase{plugin: p, active: true, onetime: true},
					options:  options}
				if err = task.reschedule(now); err != nil {
					return
				}
				tasks = append(tasks, task)
				log.Debugf("[%d] created configurator task for plugin %s", c.id, p.name())
			}
		}
	}
	for _, t := range tasks {
		p.enqueueTask(t)
	}

	if info.used.IsZero() {
		p.refcount++
		c.plugins[p] = info
	}
	info.used = now

	return nil
}

func (c *client) cleanup(plugins map[string]*pluginAgent, now time.Time) (released []*pluginAgent) {
	released = make([]*pluginAgent, 0, len(c.plugins))
	// remover references to temporary watcher tasks
	for _, p := range c.plugins {
		p.watcher = nil
	}

	// remove unused items
	for _, task := range c.exporters {
		if task.getUpdated().Before(now) {
			delete(c.exporters, task.getItem().itemid)
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
		if info, ok := c.plugins[p]; ok {
			if info.used.Before(expiry) {
				released = append(released, p)
				delete(c.plugins, p)
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

func newClient(id uint64) (b *client) {
	b = &client{
		id:        id,
		exporters: make(map[uint64]exporterTaskAccessor),
		plugins:   make(map[*pluginAgent]*pluginInfo),
	}
	return
}
