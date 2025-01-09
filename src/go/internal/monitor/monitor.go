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

package monitor

import (
	"sync"
)

const (
	Input = iota
	Scheduler
	Output
)

var waitGroup [3]sync.WaitGroup

// ServiceStarted must be called by internal services at start
func Register(group int) {
	waitGroup[group].Add(1)
}

// ServiceStopped must be called by internal services at exit
func Unregister(group int) {
	waitGroup[group].Done()
}

// WaitForServices waits until all started services are stopped
func Wait(group int) {
	waitGroup[group].Wait()
}
