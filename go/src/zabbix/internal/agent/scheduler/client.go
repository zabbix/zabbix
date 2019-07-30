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
** GNU General Public License for more detailm.
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

func (b *client) addRequest(p *pluginAgent, r *plugin.Request, sink plugin.ResultWriter, now time.Time) (err error) {
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
	if info, ok = b.plugins[p]; !ok {
		info = &pluginInfo{}
		b.plugins[p] = info
	}

	// handle Collector interface
	if c, ok := p.impl.(plugin.Collector); ok {
		if p.refcount == 0 && info.used.IsZero() {
			h := fnv.New32a()
			_, _ = h.Write([]byte(p.impl.Name()))
			log.Debugf("start collector task for plugin %s with collecting interval %d", p.impl.Name(), c.Period())
			task := &collectorTask{
				taskBase: taskBase{
					plugin:    p,
					scheduled: time.Unix(now.Unix()+int64(h.Sum32())%int64(c.Period())+1, priorityCollectorTaskNs),
					active:    true,
				}}
			p.enqueueTask(task)
		}
	}

	// handle Exporter interface
	if _, ok := p.impl.(plugin.Exporter); ok {
		if item, ok := b.items[r.Itemid]; !ok {
			item = &clientItem{itemid: r.Itemid, delay: r.Delay, key: r.Key, updated: now}
			// don't cache items for direct requests
			if r.Itemid != 0 {
				b.items[r.Itemid] = item
			}
			item.task = &exporterTask{
				taskBase: taskBase{plugin: p, scheduled: nextcheck, active: true},
				writer:   sink,
				item:     item}
			p.enqueueTask(item.task)
		} else {
			item.updated = now
			if item.delay != r.Delay && !item.unsupported {
				p.tasks.Update(item.task)
				item.task.reschedule()
			}
			item.delay = r.Delay
			item.key = r.Key
		}
	}

	// handle runner interface for inactive plugins
	if _, ok := p.impl.(plugin.Runner); ok {
		if p.refcount == 0 && info.used.IsZero() {
			log.Debugf("start starter task for plugin %s", p.impl.Name())
			task := &starterTask{
				taskBase: taskBase{
					plugin:    p,
					scheduled: now.Add(priorityStarterTaskNs),
					active:    true,
				}}
			p.enqueueTask(task)
		}
	}

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
		}
		info.watcher.requests = append(info.watcher.requests, r)
	}
	if info.used.IsZero() {
		p.refcount++
	}
	info.used = now

	return nil
}

func (b *client) cleanup(plugins map[string]*pluginAgent, now time.Time) (released []*pluginAgent) {
	released = make([]*pluginAgent, 0, len(b.plugins))
	// remover references to temporary watcher tasks
	for _, p := range b.plugins {
		p.watcher = nil
	}

	// remove unused items
	for _, item := range b.items {
		if item.updated.Before(now) {
			delete(b.items, item.itemid)
			item.task.deactivate()
		}
	}

	var expiry time.Time
	// Direct requests are handled by special client with id 0. Such requests have
	// day expiry time before used plugins are released.
	if b.id != 0 {
		expiry = now
	} else {
		expiry = now.Add(-time.Hour * 24)
	}

	// deactivate plugins
	for _, p := range plugins {
		if info, ok := b.plugins[p]; ok {
			if info.used.Before(expiry) {
				released = append(released, p)
				delete(b.plugins, p)
				p.refcount--
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
