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

package log

import "container/heap"

type logHeap []*logTask

func (h logHeap) Len() int {
	return len(h)
}

func (h logHeap) Less(i, j int) bool {
	return h[i].scheduled.Before(h[j].scheduled)
}

func (h logHeap) Swap(i, j int) {
	h[i], h[j] = h[j], h[i]
	h[i].index = i
	h[j].index = j
}

// Push -
func (h *logHeap) Push(x interface{}) {
	// Push and Pop use pointer receivers because they modify the slice's length,
	// not just its contents.
	p := x.(*logTask)
	p.index = len(*h)
	*h = append(*h, p)
}

// Pop -
func (h *logHeap) Pop() interface{} {
	old := *h
	n := len(old)
	p := old[n-1]
	*h = old[0 : n-1]
	p.index = -1
	return p
}

// Peek -
func (h *logHeap) Peek() *logTask {
	if len(*h) == 0 {
		return nil
	}
	return (*h)[0]
}

func (h *logHeap) Update(p *logTask) {
	if p.index != -1 {
		heap.Fix(h, p.index)
	}
}

func (h *logHeap) Remove(p *logTask) {
	if p.index != -1 {
		heap.Remove(h, p.index)
	}
}
