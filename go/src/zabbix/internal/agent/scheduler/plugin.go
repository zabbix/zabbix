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
	"container/heap"
	"zabbix/internal/plugin"
)

type Plugin struct {
	impl         plugin.Accessor
	tasks        performerHeap
	capacity     int
	usedCapacity int
	index        int
	refcount     int
}

func (p *Plugin) peekTask() Performer {
	if len(p.tasks) == 0 {
		return nil
	}
	return p.tasks[0]
}

func (p *Plugin) popTask() Performer {
	if len(p.tasks) == 0 {
		return nil
	}
	task := p.tasks[0]
	heap.Pop(&p.tasks)
	return task
}

func (p *Plugin) enqueueTask(performer Performer) {
	heap.Push(&p.tasks, performer)
}

func (p *Plugin) removeTask(index int) {
	heap.Remove(&p.tasks, index)
}

func (p *Plugin) reserveCapacity(performer Performer) {
	p.usedCapacity += performer.Weight()
}

func (p *Plugin) releaseCapacity(performer Performer) {
	p.usedCapacity -= performer.Weight()
}

func (p *Plugin) queued() bool {
	return p.index != -1
}

func (p *Plugin) hasCapacity() bool {
	return len(p.tasks) != 0 && p.capacity-p.usedCapacity >= p.tasks[0].Weight()
}

func (p *Plugin) active() bool {
	return p.refcount != 0
}
