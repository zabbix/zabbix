/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
)

// pluginHeap is a queue of agents, sorted by timestamps of their next scheduled tasks
type pluginHeap []*pluginAgent

func (h pluginHeap) Len() int {
	return len(h)
}

func (h pluginHeap) Less(i, j int) bool {
	if left := h[i].peekTask(); left != nil {
		if right := h[j].peekTask(); right != nil {
			return left.getScheduled().Before(right.getScheduled())
		} else {
			return false
		}
	} else {
		return true
	}
}

func (h pluginHeap) Swap(i, j int) {
	h[i], h[j] = h[j], h[i]
	h[i].index = i
	h[j].index = j
}

// Push -
func (h *pluginHeap) Push(x interface{}) {
	// Push and Pop use pointer receivers because they modify the slice's length,
	// not just its contents.
	p := x.(*pluginAgent)
	p.index = len(*h)
	*h = append(*h, p)
}

// Pop -
func (h *pluginHeap) Pop() interface{} {
	old := *h
	n := len(old)
	p := old[n-1]
	*h = old[0 : n-1]
	p.index = -1
	return p
}

// Peek -
func (h *pluginHeap) Peek() *pluginAgent {
	if len(*h) == 0 {
		return nil
	}
	return (*h)[0]
}

func (h *pluginHeap) Update(p *pluginAgent) {
	if p.index != -1 {
		heap.Fix(h, p.index)
	}
}

func (h *pluginHeap) Remove(p *pluginAgent) {
	if p.index != -1 {
		heap.Remove(h, p.index)
	}
}
