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
	"container/heap"

	"golang.zabbix.com/sdk/plugin"
)

// pluginAgent manages plugin usage
type pluginAgent struct {
	// the plugin
	impl plugin.Accessor
	// queue of tasks to perform
	tasks performerHeap
	// maximum plugin capacity
	maxCapacity int
	// used plugin capacity
	usedCapacity int
	// force active check on first configuration
	forceActiveChecksOnStart bool
	// index in plugin queue
	index int
	// refcount us used to track plugin usage by clients
	refcount int
	// usrprm is used to indicate that plugin is user parameter
	usrprm bool
}

// peekTask() returns next task in the queue without removing it from queue or nil
// if the queue is empty.
func (p *pluginAgent) peekTask() performer {
	if len(p.tasks) == 0 {
		return nil
	}
	return p.tasks[0]
}

// popTask() returns next task in the queue and removes it from queue.
// nil is returned for empty queues.
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
	return len(p.tasks) != 0 && p.maxCapacity-p.usedCapacity >= p.tasks[0].getWeight()
}

func (p *pluginAgent) active() bool {
	return p.refcount != 0
}

func (p *pluginAgent) name() string {
	return p.impl.Name()
}
