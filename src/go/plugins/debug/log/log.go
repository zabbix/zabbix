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

package log

import (
	"time"

	"git.zabbix.com/ap/plugin-support/plugin"
)

// Plugin -
type Plugin struct {
	plugin.Base
	input   chan *watchRequest
	clients map[plugin.ResultWriter][]*plugin.Request
}

type watchRequest struct {
	requests []*plugin.Request
	sink     plugin.ResultWriter
}

var impl Plugin

func (p *Plugin) run() {
	p.Debugf("activating plugin")
	ticker := time.NewTicker(time.Second)

run:
	for {
		select {
		case <-ticker.C:
			for sink, requests := range p.clients {
				for _, r := range requests {
					now := time.Now()
					value := now.Format(time.Stamp)
					lastlogsize := uint64(now.UnixNano())
					mtime := int(now.Unix())
					sink.Write(&plugin.Result{
						Itemid:      r.Itemid,
						Value:       &value,
						LastLogsize: &lastlogsize,
						Ts:          now,
						Mtime:       &mtime})
				}
			}
		case wr := <-p.input:
			if wr == nil {
				break run
			}
			p.clients[wr.sink] = wr.requests
		}
	}

	p.Debugf("plugin deactivated")
}

func (p *Plugin) Start() {
	p.Debugf("start")
	p.input = make(chan *watchRequest)
	p.clients = make(map[plugin.ResultWriter][]*plugin.Request)
	go p.run()
}

func (p *Plugin) Stop() {
	p.Debugf("stop")
	close(p.input)
}

func (p *Plugin) Watch(requests []*plugin.Request, ctx plugin.ContextProvider) {
	p.Debugf("watch")
	p.input <- &watchRequest{sink: ctx.Output(), requests: requests}
}

func (p *Plugin) Configure(global *plugin.GlobalOptions, private interface{}) {
	p.Debugf("configure")
}

func (p *Plugin) Validate(private interface{}) (err error) {
	return
}

func init() {
	plugin.RegisterMetrics(&impl, "DebugLog", "debug.log", "Returns timestamp each second.")
}
