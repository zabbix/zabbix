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

package plugin

import (
	"container/heap"
	"time"
)

type Plugin struct {
	Impl         Accessor
	Tasks        PerformerHeap
	Active       bool
	Capacity     int
	UsedCapacity int
	index        int
}

func NewPlugin(impl Accessor) *Plugin {
	plugin := Plugin{Impl: impl}

	plugin.Tasks = make(PerformerHeap, 0)
	plugin.Active = false
	plugin.Capacity = 5

	return &plugin
}

func (p *Plugin) PeekQueue() Performer {
	if len(p.Tasks) == 0 {
		return nil
	}
	return p.Tasks[0]
}

func (p *Plugin) PopQueue() Performer {
	if len(p.Tasks) == 0 {
		return nil
	}
	task := p.Tasks[0]
	heap.Pop(&p.Tasks)
	return task
}

func (p *Plugin) Enqueue(performer Performer) {
	heap.Push(&p.Tasks, performer)
}

func (p *Plugin) performTask(performer Performer, sink chan interface{}) {
	performer.Perform()
	performer.Reschedule()
	sink <- performer
}

func (p *Plugin) BeginTask(sink chan interface{}) bool {
	performer := p.PeekQueue()
	if p.Capacity-p.UsedCapacity >= performer.Weight() {
		p.UsedCapacity += performer.Weight()
		p.PopQueue()
		go p.performTask(performer, sink)
		return true
	}
	return false
}

func (p *Plugin) EndTask(performer Performer) bool {
	p.UsedCapacity -= performer.Weight()
	p.Enqueue(performer)
	return p.index == -1 && p.Capacity-p.UsedCapacity >= p.Tasks[0].Weight()
}

func (p *Plugin) Scheduled() time.Time {
	if len(p.Tasks) == 0 {
		return time.Time{}
	}
	return p.Tasks[0].Scheduled()
}
