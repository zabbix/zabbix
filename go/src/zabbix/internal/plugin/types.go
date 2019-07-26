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

import "time"

// Collector - interface for periodical metric collection
type Collector interface {
	Collect() error
	Period() int
}

// Exporter - interface for exporting collected metrics
type Exporter interface {
	Export(key string, params []string) (interface{}, error)
}

// Runner - interface for managing background processes
type Runner interface {
	Start()
	Stop()
}

// Watcher - interface for fully custom monitorin
type Watcher interface {
	Watch(requests []*Request, sink ResultWriter)
}

type ResultWriter interface {
	Write(result *Result)
}

type Result struct {
	Itemid      uint64
	Value       *string
	Ts          time.Time
	Error       error
	LastLogsize *uint64
	Mtime       *int
}

type Request struct {
	Itemid      uint64
	Key         string
	Delay       string
	LastLogsize uint64
	Mtime       int
}
