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

// watch package provides utility functionality for easier Watcher plugin implementation.
// Watcher plugin listens for events specified by the item requests and comnverts those
// events to item values. This package handles event source initialization/deinitialization,
// event filtering and conversion to item values/errors.
package watch

import (
	"sync"
	"time"

	"zabbix.com/pkg/plugin"
)

// EventSource generates events by calling Manager.Notify() method and passing arbitrary
// data. The data is converted to string values by event filter that was created by the
// event source during Manager update.
type EventSource interface {
	// initialize event source and start generating events
	Initialize() error
	// stop generating events and release internal resources allocated by event source
	Release()
	// create new event filter based on item key
	NewFilter(key string) (filter EventFilter, err error)
}

// EventProvider interface provides methods to get event source by item key. An new or existing
// event source can be returned. EventProvider is required to create Manager instance.
type EventProvider interface {
	EventSourceByKey(key string) (EventSource, error)
}

// EventFilter has two responsibilities. The first is to convert data to a string value.
// The second is optional - based on internal filter parameters (item key for example)
// it can make the data to be ignored by returning nil value.
type EventFilter interface {
	// Process processes data from event source and returns:
	//   value - data is valid and matches filter
	//   error - invalid data
	//   nothing - data is valid, but does not match the filter
	Process(data interface{}) (value *string, err error)
}

// eventWriter links event filter to output sink where the filtered results must be written
type eventWriter struct {
	output plugin.ResultWriter
	filter EventFilter
}

// Item to monitor
type Item struct {
	Key     string
	Updated time.Time
}

type Subscriber struct {
	Itemid   uint64
	Clientid uint64
}

// Client represents monitoring instance - Zabbix server or proxy
type Client struct {
	// the client ID
	ID uint64
	// the items monitored by client: itemid->Item
	Items map[uint64]*Item
}

type Manager struct {
	eventProvider EventProvider
	clients       map[uint64]*Client
	subscriptions map[EventSource]map[Subscriber]*eventWriter
	mutex         sync.Mutex
}

// Update updates monitored items for the specified client based on new requests.
func (m *Manager) Update(clientid uint64, output plugin.ResultWriter, requests []*plugin.Request) {
	var client *Client
	var ok bool

	// find or create client
	if client, ok = m.clients[clientid]; !ok {
		client = &Client{ID: clientid, Items: make(map[uint64]*Item)}
		m.clients[clientid] = client
	}

	// temporary event source error cache
	failedEventSources := make(map[EventSource]error)

	now := time.Now()
	for _, r := range requests {
		var sub map[Subscriber]*eventWriter
		subscriber := Subscriber{Clientid: client.ID, Itemid: r.Itemid}
		if item, ok := client.Items[r.Itemid]; ok {
			// remove existing subscription if item key was changed
			if item.Key != r.Key {
				if es, err := m.eventProvider.EventSourceByKey(item.Key); err == nil {
					if sub, ok := m.subscriptions[es]; ok {
						delete(sub, subscriber)
					}
					item.Key = r.Key
				} else {
					output.Write(&plugin.Result{Itemid: r.Itemid, Ts: now, Error: err})
				}
			}
			item.Updated = now
		} else {
			// register new item to be monitored
			client.Items[r.Itemid] = &Item{Key: r.Key, Updated: now}
		}
		// subscribe new or changed item
		if es, err := m.eventProvider.EventSourceByKey(r.Key); err == nil {
			// initialize new event source
			if sub, ok = m.subscriptions[es]; !ok {
				// reuse event source initialization error if the initialization did already
				// fail for this batch
				if err, ok = failedEventSources[es]; !ok {
					if err = es.Initialize(); err == nil {
						sub = make(map[Subscriber]*eventWriter)
						m.subscriptions[es] = sub
					} else {
						// cache initialization error
						failedEventSources[es] = err
					}
				}
				if err != nil {
					output.Write(&plugin.Result{Itemid: r.Itemid, Ts: now, Error: err})
				}
			}
			if sub != nil {
				if _, ok = sub[subscriber]; !ok {
					// create subscription
					if filter, err := es.NewFilter(r.Key); err != nil {
						output.Write(&plugin.Result{Itemid: r.Itemid, Ts: now, Error: err})
					} else {
						sub[subscriber] = &eventWriter{output: output, filter: filter}
					}
				}
			}
		} else {
			output.Write(&plugin.Result{Itemid: r.Itemid, Ts: now, Error: err})
		}
	}

	// remove unused subscriptions
	for itemid, item := range client.Items {
		if !item.Updated.Equal(now) {
			if es, err := m.eventProvider.EventSourceByKey(item.Key); err == nil {
				if sub, ok := m.subscriptions[es]; ok {
					delete(sub, Subscriber{Clientid: client.ID, Itemid: itemid})
				}
			}
			delete(client.Items, itemid)
		}
	}

	// release unused event sources
	for es, sub := range m.subscriptions {
		if len(sub) == 0 {
			es.Release()
			delete(m.subscriptions, es)
		}
	}
}

// Notify method notifies manager about a new event from an event source.
// Manager checks subscriptions, runs filters and writes the results to the corresponding
// output sinks.
func (m *Manager) Notify(es EventSource, data interface{}) {
	now := time.Now()
	if sub, ok := m.subscriptions[es]; ok {
		for source, writer := range sub {
			if value, err := writer.filter.Process(data); value != nil || err != nil {
				writer.output.Write(&plugin.Result{Itemid: source.Itemid, Ts: now, Value: value, Error: err})
			}
		}
	}
}

// Flush method flushes all outputs that are subscribed to the specified event source.
func (m *Manager) Flush(es EventSource) {
	if sub, ok := m.subscriptions[es]; ok {
		outputs := make([]plugin.ResultWriter, 0, len(sub))
		for _, writer := range sub {
			found := false
			for _, output := range outputs {
				if writer.output == output {
					found = true
					break
				}
			}
			if !found {
				outputs = append(outputs, writer.output)
				writer.output.Flush()
			}
		}
	}
}

func (m *Manager) Lock() {
	m.mutex.Lock()
}

func (m *Manager) Unlock() {
	m.mutex.Unlock()
}

func NewManager(e EventProvider) (manager *Manager) {
	manager = &Manager{
		clients:       make(map[uint64]*Client),
		subscriptions: make(map[EventSource]map[Subscriber]*eventWriter),
		eventProvider: e,
	}
	return
}
