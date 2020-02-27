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
	"container/heap"

	"zabbix.com/pkg/plugin"
)

type pluginAgent struct {
	impl         plugin.Accessor
	tasks        performerHeap
	capacity     int
	usedCapacity int
	index        int
	// refcount us used to track plugin usage by request batches
	refcount int
}

func (p *pluginAgent) peekTask() performer {
	if len(p.tasks) == 0 {
		return nil
	}
	return p.tasks[0]
}

func (p *pluginAgent) popTask() performer {
	if len(p.tasks) == 0 {
		return nil
	}
	task := p.tasks[0]
	heap.Pop(&p.tasks)
	return task
}

func (p *pluginAgent) enqueueTask(task performer) {
	heap.Push(&p.tasks, task)
}

func (p *pluginAgent) removeTask(index int) {
	heap.Remove(&p.tasks, index)
}

func (p *pluginAgent) reserveCapacity(task performer) {
	p.usedCapacity += task.getWeight()
}

func (p *pluginAgent) releaseCapacity(task performer) {
	p.usedCapacity -= task.getWeight()
}

func (p *pluginAgent) queued() bool {
	return p.index != -1
}

func (p *pluginAgent) hasCapacity() bool {
	return len(p.tasks) != 0 && p.capacity-p.usedCapacity >= p.tasks[0].getWeight()
}

func (p *pluginAgent) active() bool {
	return p.refcount != 0
}

func (p *pluginAgent) name() string {
	return p.impl.Name()
}
