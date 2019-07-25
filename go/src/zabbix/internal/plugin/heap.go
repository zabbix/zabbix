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

import "container/heap"

type Heap []*Plugin

func (h Heap) Len() int {
	return len(h)
}

func (h Heap) Less(i, j int) bool {
	if left := h[i].PeekQueue(); left != nil {
		if right := h[j].PeekQueue(); right != nil {
			return left.Scheduled().Before(right.Scheduled())
		} else {
			return false
		}
	} else {
		return true
	}
}

func (h Heap) Swap(i, j int) {
	h[i], h[j] = h[j], h[i]
	h[i].index = i
	h[j].index = j
}

// Push -
func (h *Heap) Push(x interface{}) {
	// Push and Pop use pointer receivers because they modify the slice's length,
	// not just its contents.
	p := x.(*Plugin)
	p.index = len(*h)
	*h = append(*h, p)
}

// Pop -
func (h *Heap) Pop() interface{} {
	old := *h
	n := len(old)
	p := old[n-1]
	*h = old[0 : n-1]
	p.index = -1
	return p
}

// Peek -
func (h *Heap) Peek() *Plugin {
	if len(*h) == 0 {
		return nil
	}
	return (*h)[0]
}

func (h *Heap) Update(p *Plugin) {
	heap.Fix(h, p.index)
}
