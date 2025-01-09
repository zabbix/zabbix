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

package filemonitor

import (
	"fmt"
	"os"

	"github.com/fsnotify/fsnotify"
	"golang.zabbix.com/agent2/pkg/itemutil"
	"golang.zabbix.com/agent2/pkg/watch"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

var impl Plugin

type watchRequest struct {
	clientid uint64
	items    []*plugin.Item
	output   plugin.ResultWriter
}

// Plugin
type Plugin struct {
	plugin.Base
	watcher      *fsnotify.Watcher
	input        chan *watchRequest
	manager      *watch.Manager
	eventSources map[string]*fileWatcher
}

type fsNotify interface {
	addPath(path string) error
	removePath(path string)
}

func init() {
	impl.eventSources = make(map[string]*fileWatcher)
	impl.manager = watch.NewManager(&impl)

	err := plugin.RegisterMetrics(&impl, "FileWatcher", "file.watch", "Monitor file contents.")
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

func (p *Plugin) run() {
	for {
		select {
		case r := <-p.input:
			if r == nil {
				return
			}
			p.manager.Update(r.clientid, r.output, r.items)
		case event := <-p.watcher.Events:
			if event.Op&fsnotify.Write == fsnotify.Write {
				var b []byte
				var v interface{}
				if b, v = os.ReadFile(event.Name); v == nil {
					v = b
				}
				if es, ok := p.eventSources[event.Name]; ok {
					p.manager.Notify(es, v)
				}
			}
		}
	}
}

func (p *Plugin) Watch(items []*plugin.Item, ctx plugin.ContextProvider) {
	p.input <- &watchRequest{clientid: ctx.ClientID(), items: items, output: ctx.Output()}
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
	path     string
	fsnotify fsNotify
}

type eventFilter struct{}

func (w *eventFilter) Process(v interface{}) (value *string, err error) {
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

func (w *fileWatcher) Initialize() (err error) {
	return w.fsnotify.addPath(w.path)
}

func (w *fileWatcher) Release() {
	w.fsnotify.removePath(w.path)
}

func (w *fileWatcher) NewFilter(key string) (filter watch.EventFilter, err error) {
	return &eventFilter{}, nil
}

func (p *Plugin) EventSourceByKey(key string) (es watch.EventSource, err error) {
	var params []string
	if _, params, err = itemutil.ParseKey(key); err != nil {
		return
	}
	watcher, ok := p.eventSources[params[0]]
	if !ok {
		watcher = &fileWatcher{path: params[0], fsnotify: p}
		p.eventSources[key] = watcher
	}
	return watcher, nil
}

func (p *Plugin) addPath(path string) error {
	return p.watcher.Add(path)
}

func (p *Plugin) removePath(path string) {
	_ = p.watcher.Remove(path)
	delete(p.eventSources, path)
}
