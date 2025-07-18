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
	"time"
)

// Performer interface provides common access to plugin tasks.
type Performer interface {
	// returns the task plugin
	getPlugin() *pluginAgent
	// sets the task plugin
	setPlugin(p *pluginAgent)
	// performs the task, this function is called in a separate goroutine
	perform(s Scheduler)
	// reschedules the task, returns false if the task has been expired
	reschedule(now time.Time) error
	// returns time the task has been scheduled to perform
	getScheduled() time.Time
	// returns task weight
	getWeight() int
	// returns task index in plugin task queue
	getIndex() int
	// sets task index in the plugin task queue
	setIndex(index int)
	// returns true if the task is active
	isActive() bool
	// deactivates task, removing from plugin task queue if necessary
	deactivate()
	// true if the task has to be rescheduled after performing
	isRecurring() bool
	// true if item key equals
	isItemKeyEqual(itemkey string) bool
}

// performerHeap -
type performerHeap []Performer

func (h performerHeap) Len() int {
	return len(h)
}

func (h performerHeap) Less(i, j int) bool {
	return h[i].getScheduled().Before(h[j].getScheduled())
}

func (h performerHeap) Swap(i, j int) {
	h[i], h[j] = h[j], h[i]
	h[i].setIndex(i)
	h[j].setIndex(j)
}

// Push -
func (h *performerHeap) Push(x interface{}) {
	// Push and Pop use pointer receivers because they modify the slice's length,
	// not just its contents.
	p := x.(Performer)
	p.setIndex(len(*h))
	*h = append(*h, p)
}

// Pop -
func (h *performerHeap) Pop() interface{} {
	old := *h
	n := len(old)
	p := old[n-1]
	// clear slice slot, so the performer can be garbage collected later
	old[n-1] = nil
	*h = old[0 : n-1]
	p.setIndex(-1)
	return p
}

// Peek -
func (h *performerHeap) Peek() Performer {
	if len(*h) == 0 {
		return nil
	}
	return (*h)[0]
}

func (h *performerHeap) Update(p Performer) {
	if p.getIndex() != -1 {
		heap.Fix(h, p.getIndex())
	}
}
