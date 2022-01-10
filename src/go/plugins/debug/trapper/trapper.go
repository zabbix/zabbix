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

package trapper

import (
	"fmt"
	"io/ioutil"

	"net"
	"regexp"
	"strconv"

	"zabbix.com/pkg/itemutil"
	"zabbix.com/pkg/log"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/watch"
)

// Plugin
type Plugin struct {
	plugin.Base
	manager   *watch.Manager
	listeners map[int]*trapListener
}

var impl Plugin

func (p *Plugin) Watch(requests []*plugin.Request, ctx plugin.ContextProvider) {
	p.manager.Lock()
	p.manager.Update(ctx.ClientID(), ctx.Output(), requests)
	p.manager.Unlock()
}

type trapListener struct {
	port     int
	listener net.Listener
	manager  *watch.Manager
	log      log.Logger
}

func (t *trapListener) run() {
	for {
		conn, err := t.listener.Accept()
		if err != nil {
			if nerr, ok := err.(net.Error); ok && !nerr.Temporary() {
				break
			}
			continue
		}
		if b, err := ioutil.ReadAll(conn); err == nil {
			t.manager.Lock()
			t.manager.Notify(t, b)
			t.manager.Flush(t)
			t.manager.Unlock()
		}
		conn.Close()
	}
}

func (t *trapListener) Initialize() (err error) {
	t.log.Debugf("start listening on %d", t.port)
	if t.listener, err = net.Listen("tcp", fmt.Sprintf(":%d", t.port)); err != nil {
		return
	}
	go t.run()
	return nil
}

func (t *trapListener) Release() {
	t.log.Debugf("stop listening on %d", t.port)
	t.listener.Close()
}

type trapFilter struct {
	pattern *regexp.Regexp
}

func (f *trapFilter) Process(v interface{}) (value *string, err error) {
	if b, ok := v.([]byte); !ok {
		err = fmt.Errorf("unexpected traper conversion input type %T", v)
	} else {
		if f.pattern == nil || f.pattern.Match(b) {
			tmp := string(b)
			value = &tmp
		}
	}
	return
}

func (t *trapListener) NewFilter(key string) (filter watch.EventFilter, err error) {
	var params []string
	if _, params, err = itemutil.ParseKey(key); err != nil {
		return
	}
	var pattern *regexp.Regexp
	if len(params) > 1 {
		if pattern, err = regexp.Compile(params[1]); err != nil {
			return
		}
	}
	return &trapFilter{pattern: pattern}, nil
}

func (p *Plugin) EventSourceByKey(key string) (es watch.EventSource, err error) {
	var params []string
	if _, params, err = itemutil.ParseKey(key); err != nil {
		return
	}
	var port int
	if port, err = strconv.Atoi(params[0]); err != nil {
		return
	}
	var ok bool
	var listener *trapListener
	if listener, ok = p.listeners[port]; !ok {
		listener = &trapListener{port: port, manager: p.manager, log: p}
		p.listeners[port] = listener
	}
	return listener, nil
}

func init() {
	impl.manager = watch.NewManager(&impl)
	impl.listeners = make(map[int]*trapListener)

	plugin.RegisterMetrics(&impl, "DebugTrapper", "debug.trap", "Listen on port for incoming TCP data.")
}
