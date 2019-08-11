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

package watch

import (
	"time"
	"zabbix/internal/plugin"
)

// describes single event source - listen port for traps, file name for file content monitoring etc
type EventSource interface {
	// unique event source identifier
	URI() string
	// start generating events
	Subscribe() error
	// stop generating events
	Unsubscribe()
}

// EventProvider interface provides methods to get event source by item key or event source URI.
// The event source can be either cached by provider or created a new one upon every request.
// In the second case it would be most like a wrapper to bridge watch Manager with event generator.
type EventProvider interface {
	EventSourceByKey(key string) (EventSource, error)
	EventSourceByURI(uri string) (EventSource, error)
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
	subscriptions map[string]map[Source]plugin.ResultWriter
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
		var sub map[Source]plugin.ResultWriter
		source := Source{Clientid: client.ID, Itemid: r.Itemid}
		if item, ok := client.Items[r.Itemid]; ok {
			if item.Key != r.Key {
				if event, err := m.eventProvider.EventSourceByKey(item.Key); err != nil {

					if sub, ok := m.subscriptions[event.URI()]; ok {
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
					sub = make(map[Source]plugin.ResultWriter)
					m.subscriptions[es.URI()] = sub
				} else {
					output.Write(&plugin.Result{Itemid: r.Itemid, Ts: now, Error: err})
				}
			}
			if sub != nil {
				if _, ok = sub[source]; !ok {
					sub[source] = output
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

func (m *Manager) Notify(es EventSource, value *string, err error) {
	now := time.Now()
	if sub, ok := m.subscriptions[es.URI()]; ok {
		for source, output := range sub {
			output.Write(&plugin.Result{Itemid: source.Itemid, Ts: now, Value: value, Error: err})
			output.Flush()
		}
	}
}

func NewManager(e EventProvider) (manager *Manager) {
	manager = &Manager{
		clients:       make(map[uint64]*Client),
		subscriptions: make(map[string]map[Source]plugin.ResultWriter),
		eventProvider: e,
	}
	return
}
