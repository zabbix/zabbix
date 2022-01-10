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

package plugin

import (
	"errors"
	"sync/atomic"
	"time"
)

const (
	DefaultCapacity = 100
)

var UnsupportedMetricError error

// Collector - interface for periodical metric collection
type Collector interface {
	Collect() error
	Period() int
}

// Exporter - interface for exporting collected metrics
type Exporter interface {
	// Export method exports data based on the key 'key' and its parameters 'params'.
	Export(key string, params []string, context ContextProvider) (interface{}, error)
}

// Runner - interface for managing background processes
type Runner interface {
	// Start method activates plugin.
	Start()
	// Stop method deactivates pluing.
	Stop()
}

// Watcher - interface for fully custom monitoring
type Watcher interface {
	// Watch method instructs plugin to watch for events based on item configuration in 'requests'.
	Watch(requests []*Request, context ContextProvider)
}

// Configurator - interface for plugin configuration in agent conf files
type Configurator interface {
	// Configure method passes global and private plugin configuration after it has been activated.
	Configure(globalOptions *GlobalOptions, privateOptions interface{})
	// Validate method validates private plugin configuration during agent startup.
	Validate(privateOptions interface{}) error
}

type ResultWriter interface {
	Write(result *Result)
	Flush()
	SlotsAvailable() int
	PersistSlotsAvailable() int
}

type Meta struct {
	lastLogsize uint64
	mtime       int32
	Data        interface{}
}

func (m *Meta) SetLastLogsize(value uint64) {
	atomic.StoreUint64(&m.lastLogsize, value)
}

func (m *Meta) LastLogsize() uint64 {
	return atomic.LoadUint64(&m.lastLogsize)
}

func (m *Meta) SetMtime(value int32) {
	atomic.StoreInt32(&m.mtime, value)
}

func (m *Meta) Mtime() int32 {
	return atomic.LoadInt32(&m.mtime)
}

type RegexpMatcher interface {
	Match(value string, pattern string, mode int, output_template *string) (match bool, output string)
}

type ContextProvider interface {
	ClientID() uint64
	ItemID() uint64
	Output() ResultWriter
	Meta() *Meta
	GlobalRegexp() RegexpMatcher
}

type Result struct {
	Itemid uint64
	Value  *string

	// additional windows eventlog fields
	EventSource    *string
	EventID        *int
	EventTimestamp *int
	EventSeverity  *int

	Ts          time.Time
	Error       error
	LastLogsize *uint64
	Mtime       *int
	Persistent  bool
}

type Request struct {
	Itemid      uint64  `json:"itemid"`
	Key         string  `json:"key"`
	Delay       string  `json:"delay"`
	LastLogsize *uint64 `json:"lastlogsize"`
	Mtime       *int    `json:"mtime"`
}

// GlobalOptions are global agent configuration parameters that can be accessed by plugins.
// In most cases it's recommended to allow plugins overriding global configuration parameters
// they are using with plugin specific parameters.
type GlobalOptions struct {
	Timeout  int
	SourceIP string
}

func init() {
	UnsupportedMetricError = errors.New("Unsupported item key.")
}
