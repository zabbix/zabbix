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

package log

import (
	"time"

	"golang.zabbix.com/agent2/internal/agent/scheduler"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

var impl Plugin

// Plugin -
type Plugin struct {
	plugin.Base
	input   chan *watchRequest
	clients map[plugin.ResultWriter][]*scheduler.Request
}

type watchRequest struct {
	requests []*scheduler.Request
	sink     plugin.ResultWriter
}

func init() {
	err := plugin.RegisterMetrics(&impl, "DebugLog", "debug.log", "Returns timestamp each second.")
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

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
						Mtime:       &mtime,
					})
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
	p.clients = make(map[plugin.ResultWriter][]*scheduler.Request)
	go p.run()
}

func (p *Plugin) Stop() {
	p.Debugf("stop")
	close(p.input)
}

func (p *Plugin) Watch(requests []*scheduler.Request, ctx plugin.ContextProvider) {
	p.Debugf("watch")
	p.input <- &watchRequest{sink: ctx.Output(), requests: requests}
}

func (p *Plugin) Configure(global *plugin.GlobalOptions, private interface{}) {
	p.Debugf("configure")
}

func (p *Plugin) Validate(private interface{}) (err error) {
	return
}
