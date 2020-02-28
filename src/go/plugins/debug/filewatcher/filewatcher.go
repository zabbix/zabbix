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

package filemonitor

import (
	"fmt"
	"io/ioutil"

	"zabbix.com/pkg/itemutil"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/watch"

	"github.com/fsnotify/fsnotify"
)

type watchRequest struct {
	clientid uint64
	targets  []*plugin.Request
	output   plugin.ResultWriter
}

// Plugin
type Plugin struct {
	plugin.Base
	watcher *fsnotify.Watcher
	input   chan *watchRequest
	manager *watch.Manager
}

var impl Plugin

func (p *Plugin) run() {
	for {
		select {
		case r := <-p.input:
			if r == nil {
				return
			}
			p.manager.Update(r.clientid, r.output, r.targets)
		case event := <-p.watcher.Events:
			if event.Op&fsnotify.Write == fsnotify.Write {
				var b []byte
				var v interface{}
				if b, v = ioutil.ReadFile(event.Name); v == nil {
					v = b
				}
				es, _ := p.EventSourceByURI(event.Name)
				p.manager.Notify(es, v)
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

type fileWatcher struct {
	path    string
	watcher *fsnotify.Watcher
}

type eventFilter struct {
}

func (w *eventFilter) Convert(v interface{}) (value *string, err error) {
	if b, ok := v.([]byte); !ok {
		if err, ok = v.(error); !ok {
			err = fmt.Errorf("unexpected input type %T", v)
		}
		return
	} else {
		tmp := string(b)
		return &tmp, nil
	}
}

func (w *fileWatcher) URI() (uri string) {
	return w.path
}

func (w *fileWatcher) Subscribe() (err error) {
	return w.watcher.Add(w.path)
}

func (w *fileWatcher) Unsubscribe() {
	_ = w.watcher.Remove(w.path)
}

func (w *fileWatcher) NewFilter(key string) (filter watch.EventFilter, err error) {
	return &eventFilter{}, nil
}

func (p *Plugin) EventSourceByURI(uri string) (es watch.EventSource, err error) {
	return &fileWatcher{path: uri, watcher: p.watcher}, nil
}

func (p *Plugin) EventSourceByKey(key string) (es watch.EventSource, err error) {
	var params []string
	if _, params, err = itemutil.ParseKey(key); err != nil {
		return
	}
	return &fileWatcher{path: params[0], watcher: p.watcher}, nil
}

func init() {
	impl.manager = watch.NewManager(&impl)

	plugin.RegisterMetrics(&impl, "FileWatcher", "file.watch", "Monitor file contents")
}
