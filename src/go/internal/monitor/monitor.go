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

package monitor

import (
	"sync"
)

var waitGroup sync.WaitGroup

// ServiceStarted must be called by internal services at start
func Register() {
	waitGroup.Add(1)
}

// ServiceStopped must be called by internal services at exit
func Unregister() {
	waitGroup.Done()
}

// WaitForServices waits until all started services are stopped
func Wait() {
	waitGroup.Wait()
}
