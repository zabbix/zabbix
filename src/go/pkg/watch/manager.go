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

package watch

import (
	"sync"
	"time"

	"zabbix.com/pkg/plugin"
)

// describes single event source - listen port for traps, file name for file content monitoring etc
type EventSource interface {
	// unique event source identifier
	URI() string
	// start generating events
	Subscribe() error
	// stop generating events
	Unsubscribe()
	// create new event writer
	NewFilter(key string) (filter EventFilter, err error)
}

// EventProvider interface provides methods to get event source by item key or event source URI.
// The event source can be either cached by provider or created a new one upon every request.
// In the second case it would be most like a wrapper to bridge watch Manager with event generator.
type EventProvider interface {
	EventSourceByKey(key string) (EventSource, error)
	EventSourceByURI(uri string) (EventSource, error)
}

type EventFilter interface {
	Convert(data interface{}) (value *string, err error)
}

type EventWriter struct {
	output plugin.ResultWriter
	filter EventFilter
}

type Item struct {
	Key     string
	Updated time.Time
}

type Source struct {
	Itemid   uint64
	Clientid uint64
}

type Client struct {
	ID    uint64
	Items map[uint64]*Item
}

type Manager struct {
	eventProvider EventProvider
	clients       map[uint64]*Client
	subscriptions map[string]map[Source]*EventWriter
	mutex         sync.Mutex
}

func (m *Manager) Update(clientid uint64, output plugin.ResultWriter, requests []*plugin.Request) {
	var client *Client
	var ok bool
	if client, ok = m.clients[clientid]; !ok {
		client = &Client{ID: clientid, Items: make(map[uint64]*Item)}
		m.clients[clientid] = client
	}

	now := time.Now()
	for _, r := range requests {
		var sub map[Source]*EventWriter
		source := Source{Clientid: client.ID, Itemid: r.Itemid}
		if item, ok := client.Items[r.Itemid]; ok {
			if item.Key != r.Key {
				if es, err := m.eventProvider.EventSourceByKey(item.Key); err == nil {
					if sub, ok := m.subscriptions[es.URI()]; ok {
						delete(sub, source)
					}
					item.Key = r.Key
				} else {
					output.Write(&plugin.Result{Itemid: r.Itemid, Ts: now, Error: err})
				}
			}
			item.Updated = now
		} else {
			client.Items[r.Itemid] = &Item{Key: r.Key, Updated: now}
		}
		if es, err := m.eventProvider.EventSourceByKey(r.Key); err == nil {
			if sub, ok = m.subscriptions[es.URI()]; !ok {
				if err := es.Subscribe(); err == nil {
					sub = make(map[Source]*EventWriter)
					m.subscriptions[es.URI()] = sub
				} else {
					output.Write(&plugin.Result{Itemid: r.Itemid, Ts: now, Error: err})
				}
			}
			if sub != nil {
				if _, ok = sub[source]; !ok {
					if filter, err := es.NewFilter(r.Key); err != nil {
						output.Write(&plugin.Result{Itemid: r.Itemid, Ts: now, Error: err})
					} else {
						sub[source] = &EventWriter{output: output, filter: filter}
					}
				}
			}
		} else {
			output.Write(&plugin.Result{Itemid: r.Itemid, Ts: now, Error: err})
		}
	}

	for itemid, item := range client.Items {
		if !item.Updated.Equal(now) {
			if sub, ok := m.subscriptions[item.Key]; ok {
				delete(sub, Source{Clientid: client.ID, Itemid: itemid})
			}
			delete(client.Items, itemid)
		}
	}

	for uri, sub := range m.subscriptions {
		if len(sub) == 0 {
			if es, err := m.eventProvider.EventSourceByURI(uri); err != nil {
				es.Unsubscribe()
			}
			delete(m.subscriptions, uri)
		}
	}
}

func (m *Manager) Notify(es EventSource, data interface{}) {
	now := time.Now()
	if sub, ok := m.subscriptions[es.URI()]; ok {
		outputs := make(map[plugin.ResultWriter]bool)
		for source, writer := range sub {
			if value, err := writer.filter.Convert(data); value != nil || err != nil {
				writer.output.Write(&plugin.Result{Itemid: source.Itemid, Ts: now, Value: value, Error: err})
				outputs[writer.output] = true
			}
		}
		for output := range outputs {
			output.Flush()
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
		subscriptions: make(map[string]map[Source]*EventWriter),
		eventProvider: e,
	}
	return
}
