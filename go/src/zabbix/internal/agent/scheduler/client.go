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
	"fmt"
	"hash/fnv"
	"time"
	"zabbix/internal/plugin"
	"zabbix/pkg/itemutil"
	"zabbix/pkg/log"
)

type clientItem struct {
	itemid      uint64
	delay       string
	unsupported bool
	key         string
	task        performer
	updated     time.Time
}

type pluginInfo struct {
	used    time.Time
	watcher *watcherTask
}

type client struct {
	id      uint64
	items   map[uint64]*clientItem
	plugins map[*pluginAgent]*pluginInfo
}

func (c *client) addRequest(p *pluginAgent, r *plugin.Request, sink plugin.ResultWriter, now time.Time) (err error) {
	var nextcheck time.Time
	// calculate nextcheck for normal requests, while direct requests are executed immediately
	if r.Itemid != 0 {
		if nextcheck, err = itemutil.GetNextcheck(r.Itemid, r.Delay, false, now); err != nil {
			return fmt.Errorf("Cannot calculate nextcheck: %s", err.Error())
		}
	} else {
		nextcheck = now
	}
	nextcheck.Add(priorityExporterTaskNs)

	var info *pluginInfo
	var ok bool
	if info, ok = c.plugins[p]; !ok {
		info = &pluginInfo{}
		c.plugins[p] = info
	}

	// handle Collector interface
	if col, ok := p.impl.(plugin.Collector); ok {
		if p.refcount == 0 && info.used.IsZero() {
			h := fnv.New32a()
			_, _ = h.Write([]byte(p.impl.Name()))
			task := &collectorTask{
				taskBase: taskBase{
					plugin:    p,
					scheduled: time.Unix(now.Unix()+int64(h.Sum32())%int64(col.Period())+1, priorityCollectorTaskNs),
					active:    true,
				}}
			p.enqueueTask(task)
			log.Debugf("[%d] created collector task for plugin %s with collecting interval %d", c.id, p.name(),
				col.Period())
		}
	}

	// handle Exporter interface
	if _, ok := p.impl.(plugin.Exporter); ok {
		if item, ok := c.items[r.Itemid]; !ok {
			item = &clientItem{itemid: r.Itemid, delay: r.Delay, key: r.Key, updated: now}
			// don't cache items for direct requests
			if r.Itemid != 0 {
				c.items[r.Itemid] = item
			}
			item.task = &exporterTask{
				taskBase: taskBase{plugin: p, scheduled: nextcheck, active: true},
				writer:   sink,
				item:     item}
			p.enqueueTask(item.task)
			log.Debugf("[%d] created exporter task for plugin %s", c.id, p.name())
		} else {
			item.updated = now
			if item.delay != r.Delay && !item.unsupported {
				p.tasks.Update(item.task)
				item.task.reschedule()
				log.Debugf("[%d] updated exporter task for plugin %s", c.id, p.name())
			}
			item.delay = r.Delay
			item.key = r.Key
		}
	}

	// handle runner interface for inactive plugins
	if _, ok := p.impl.(plugin.Runner); ok {
		if p.refcount == 0 && info.used.IsZero() {
			task := &starterTask{
				taskBase: taskBase{
					plugin:    p,
					scheduled: now.Add(priorityStarterTaskNs),
					active:    true,
				}}
			p.enqueueTask(task)
			log.Debugf("[%d] created starter task for plugin %s", c.id, p.name())
		}
	}

	// Watcher plugins are not supported by direct requests
	if c.id != 0 {
		// handle Watcher interface
		if _, ok := p.impl.(plugin.Watcher); ok {
			if info.watcher == nil {
				info.watcher = &watcherTask{
					taskBase: taskBase{
						plugin:    p,
						scheduled: now.Add(priorityWatcherTaskNs),
						active:    true,
					},
					sink:     sink,
					requests: make([]*plugin.Request, 0, 1),
				}
				p.enqueueTask(info.watcher)
				log.Debugf("[%d] created watcher task for plugin %s", c.id, p.name())
			}
			info.watcher.requests = append(info.watcher.requests, r)
		}
	}
	if info.used.IsZero() {
		p.refcount++
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
	for _, item := range c.items {
		if item.updated.Before(now) {
			delete(c.items, item.itemid)
			item.task.deactivate()
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
						time.Now().Format(time.Stamp))
				}
			}
		}
	}
	return
}

func newClient(id uint64) (b *client) {
	b = &client{
		id:      id,
		items:   make(map[uint64]*clientItem),
		plugins: make(map[*pluginAgent]*pluginInfo),
	}
	return
}
