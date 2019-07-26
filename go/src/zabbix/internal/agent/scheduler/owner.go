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
	"hash/fnv"
	"time"
	"zabbix/internal/plugin"
	"zabbix/pkg/itemutil"
	"zabbix/pkg/log"
)

type Item struct {
	itemid      uint64
	delay       string
	unsupported bool
	key         string
	task        Performer
	updated     time.Time
}

type PluginInfo struct {
	used    time.Time
	watcher *WatcherTask
}

type Owner struct {
	items   map[uint64]*Item
	plugins map[*Plugin]*PluginInfo
}

func (o *Owner) processRequest(p *Plugin, r *plugin.Request, sink plugin.ResultWriter, now time.Time) (err error) {
	var nextcheck time.Time
	if nextcheck, err = itemutil.GetNextcheck(r.Itemid, r.Delay, false, now); err != nil {
		return
	}
	nextcheck.Add(priorityExporterTaskNs)

	var info *PluginInfo
	var ok bool
	if info, ok = o.plugins[p]; !ok {
		info = &PluginInfo{}
		o.plugins[p] = info
	}

	// handle Collector interface
	if c, ok := p.impl.(plugin.Collector); ok {
		if p.refcount == 0 && info.used.IsZero() {
			h := fnv.New32a()
			_, _ = h.Write([]byte(p.impl.Name()))
			log.Debugf("start collector task for plugin %s with collecting interval %d", p.impl.Name(), c.Period())
			task := &CollectorTask{
				Task: Task{
					plugin:    p,
					scheduled: time.Unix(now.Unix()+int64(h.Sum32())%int64(c.Period())+1, priorityCollectorTaskNs),
					active:    true,
				}}
			p.enqueueTask(task)
		}
	}

	// handle Exporter interface
	if _, ok := p.impl.(plugin.Exporter); ok {
		if item, ok := o.items[r.Itemid]; !ok {
			item = &Item{itemid: r.Itemid, delay: r.Delay, key: r.Key, updated: now}
			o.items[r.Itemid] = item
			item.task = &ExporterTask{
				Task:   Task{plugin: p, scheduled: nextcheck, active: true},
				writer: sink,
				item:   item}
			p.enqueueTask(item.task)
		} else {
			item.updated = now
			if item.delay != r.Delay && !item.unsupported {
				p.tasks.Update(item.task)
				item.task.Reschedule()
			}
			item.delay = r.Delay
			item.key = r.Key
		}
	}

	// handle runner interface for inactive plugins
	if _, ok := p.impl.(plugin.Runner); ok {
		if p.refcount == 0 && info.used.IsZero() {
			log.Debugf("start starter task for plugin %s", p.impl.Name())
			task := &StarterTask{
				Task: Task{
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
			info.watcher = &WatcherTask{
				Task: Task{
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

func (o *Owner) releasePlugins(plugins map[string]*Plugin, now time.Time) (released []*Plugin) {
	released = make([]*Plugin, 0, len(o.plugins))
	// remover references to temporary watcher tasks
	for _, p := range o.plugins {
		p.watcher = nil
	}

	// remove unused items
	for _, item := range o.items {
		if item.updated.Before(now) {
			delete(o.items, item.itemid)
			item.task.Deactivate()
		}
	}

	// deactivate plugins
	for _, p := range plugins {
		if info, ok := o.plugins[p]; ok {
			if info.used.Before(now) {
				released = append(released, p)
				delete(o.plugins, p)
				p.refcount--
			}
		}
	}
	return
}

func newOwner() (owner *Owner) {
	owner = &Owner{
		items:   make(map[uint64]*Item),
		plugins: make(map[*Plugin]*PluginInfo),
	}
	return
}
