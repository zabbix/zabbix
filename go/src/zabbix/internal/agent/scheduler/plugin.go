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
	"time"
	"zabbix/internal/plugin"
)

type Plugin struct {
	impl         plugin.Accessor
	tasks        performerHeap
	active       bool
	capacity     int
	usedCapacity int
	index        int
}

func NewPlugin(impl plugin.Accessor) *Plugin {
	plugin := Plugin{impl: impl}

	plugin.tasks = make(performerHeap, 0)
	plugin.active = false
	plugin.capacity = 5

	return &plugin
}

func (p *Plugin) PeekQueue() Performer {
	if len(p.tasks) == 0 {
		return nil
	}
	return p.tasks[0]
}

func (p *Plugin) PopQueue() Performer {
	if len(p.tasks) == 0 {
		return nil
	}
	task := p.tasks[0]
	heap.Pop(&p.tasks)
	return task
}

func (p *Plugin) Enqueue(performer Performer) {
	heap.Push(&p.tasks, performer)
}

func (p *Plugin) Remove(index int) {
	heap.Remove(&p.tasks, index)
}

func (p *Plugin) BeginTask(s Scheduler) bool {
	performer := p.PeekQueue()
	if p.capacity-p.usedCapacity >= performer.Weight() {
		p.usedCapacity += performer.Weight()
		p.PopQueue()
		performer.Perform(s)
		return true
	}
	return false
}

// EndTask enqueues finished task, updates plugin capacity and returns true
// if the plugin itself must be enqueued
func (p *Plugin) EndTask(performer Performer) bool {
	p.usedCapacity -= performer.Weight()
	if !performer.Active() {
		return false
	}
	p.Enqueue(performer)
	return p.index == -1 && p.capacity-p.usedCapacity >= p.tasks[0].Weight()
}

func (p *Plugin) Scheduled() time.Time {
	if len(p.tasks) == 0 {
		return time.Time{}
	}
	return p.tasks[0].Scheduled()
}
