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
)

type Performer interface {
	Plugin() *Plugin
	Perform(s Scheduler)
	Reschedule()
	Scheduled() time.Time
	Weight() int
	Index() int
	SetIndex(index int)
}

// performerHeap -
type performerHeap []Performer

func (h performerHeap) Len() int {
	return len(h)
}

func (h performerHeap) Less(i, j int) bool {
	return h[i].Scheduled().Before(h[j].Scheduled())
}

func (h performerHeap) Swap(i, j int) {
	h[i], h[j] = h[j], h[i]
	h[i].SetIndex(i)
	h[j].SetIndex(j)
}

// Push -
func (h *performerHeap) Push(x interface{}) {
	// Push and Pop use pointer receivers because they modify the slice's length,
	// not just its contents.
	p := x.(Performer)
	p.SetIndex(len(*h))
	*h = append(*h, p)
}

// Pop -
func (h *performerHeap) Pop() interface{} {
	old := *h
	n := len(old)
	p := old[n-1]
	*h = old[0 : n-1]
	p.SetIndex(-1)
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
	if p.Index() != -1 {
		heap.Fix(h, p.Index())
	}
}
