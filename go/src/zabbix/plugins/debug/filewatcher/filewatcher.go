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

package filemonitor

import (
	"errors"
	"io/ioutil"
	"time"
	"zabbix/internal/plugin"
	"zabbix/pkg/itemutil"

	"github.com/fsnotify/fsnotify"
)

type watchItem struct {
	filepath string
	updated  time.Time
}

type watchRequest struct {
	clientid uint64
	targets  []*plugin.Request
	output   plugin.ResultWriter
}

type watchClient struct {
	id    uint64
	items map[uint64]*watchItem
}

type watchID struct {
	itemid   uint64
	clientid uint64
}

// Plugin
type Plugin struct {
	plugin.Base
	watcher *fsnotify.Watcher
	input   chan *watchRequest
	clients map[uint64]*watchClient
	files   map[string]map[watchID]plugin.ResultWriter
}

var impl Plugin

func (p *Plugin) updateClient(request *watchRequest, now time.Time) {
	var client *watchClient
	var ok bool
	if client, ok = p.clients[request.clientid]; !ok {
		client = &watchClient{id: request.clientid, items: make(map[uint64]*watchItem)}
		p.clients[request.clientid] = client
	}

	for _, r := range request.targets {
		_, params, err := itemutil.ParseKey(r.Key)
		if err != nil {
			request.output.Write(&plugin.Result{Itemid: r.Itemid, Ts: now, Error: err})
			continue
		}
		if len(params) != 1 {
			err = errors.New("Invalid number of parameters.")
			request.output.Write(&plugin.Result{Itemid: r.Itemid, Ts: now, Error: err})
			continue
		}
		var watchers map[watchID]plugin.ResultWriter
		watchid := watchID{clientid: client.id, itemid: r.Itemid}
		if item, ok := client.items[r.Itemid]; ok {
			if item.filepath != params[0] {
				if watchers, ok = p.files[item.filepath]; ok {
					delete(watchers, watchid)
				}
				item.filepath = params[0]
			}
			item.updated = now
		} else {
			client.items[r.Itemid] = &watchItem{filepath: params[0], updated: now}
		}
		if watchers, ok = p.files[params[0]]; !ok {
			if err := p.watcher.Add(params[0]); err != nil {
				request.output.Write(&plugin.Result{Itemid: r.Itemid, Ts: now, Error: err})
				p.Debugf(`cannot watch file "%s": %s`, params[0], err)
			} else {
				watchers = make(map[watchID]plugin.ResultWriter)
				p.files[params[0]] = watchers
				p.Debugf(`start watching file "%s"`, params[0])
			}
		}
		if watchers != nil {
			if _, ok = watchers[watchid]; !ok {
				watchers[watchid] = request.output
			}
		}
	}

	for itemid, item := range client.items {
		if !item.updated.Equal(now) {
			if watchers, ok := p.files[item.filepath]; ok {
				delete(watchers, watchID{clientid: client.id, itemid: itemid})
			}
			delete(client.items, itemid)
		}
	}

	for path, watchers := range p.files {
		if len(watchers) == 0 {
			p.Debugf(`stop watching file "%s"`, path)
			if err := p.watcher.Remove(path); err != nil {
				p.Debugf(`cannot remove file "%s" from fsnotify watcher: %s`, path, err)
			}
			delete(p.files, path)
		}
	}
}

func (p *Plugin) run() {

run:
	for {
		select {
		case r := <-p.input:
			if r == nil {
				break run
			}
			p.updateClient(r, time.Now())
		case event := <-p.watcher.Events:
			if event.Op&fsnotify.Write == fsnotify.Write {
				if watchers, ok := p.files[event.Name]; ok {
					var value *string
					var err error
					var b []byte
					if b, err = ioutil.ReadFile(event.Name); err == nil {
						tmp := string(b)
						value = &tmp
					}
					now := time.Now()
					for source, output := range watchers {
						output.Write(&plugin.Result{Itemid: source.itemid, Ts: now, Value: value, Error: err})
						output.Flush()
					}
				}
			}
		}
	}
}

func (p *Plugin) Watch(requests []*plugin.Request, ctx plugin.ContextProvider) {
	p.input <- &watchRequest{clientid: ctx.ClientID(), targets: requests, output: ctx.Output()}
}

func (p *Plugin) Start() {
	p.input = make(chan *watchRequest, 10)
	var err error
	p.watcher, err = fsnotify.NewWatcher()
	if err != nil {
		p.Errf("cannot create file watcher: %s", err)
	}
	go p.run()
}

func (p *Plugin) Stop() {
	if p.watcher != nil {
		p.input <- nil
		close(p.input)
		p.watcher.Close()
		p.watcher = nil
	}
}

func init() {
	impl.clients = make(map[uint64]*watchClient)
	impl.files = make(map[string]map[watchID]plugin.ResultWriter)

	plugin.RegisterMetric(&impl, "filewatcher", "file.watch", "Monitor file contents")
}
