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

package agent

import (
	"time"
	"zabbix/internal/monitor"
	"zabbix/internal/plugin"
	"zabbix/pkg/log"
)

type Scheduler struct {
	input   chan interface{}
	clients map[plugin.ResultWriter]*Client
}

type Client struct {
	writer     plugin.ResultWriter
	updateTime time.Time
}

type UpdateRequest struct {
	writer   plugin.ResultWriter
	requests []*plugin.Request
}

func (s *Scheduler) processUpdateRequest(update *UpdateRequest) {
	log.Debugf("processing update request from instance %p (%d requests)", update.writer, len(update.requests))

	var client *Client
	var ok bool
	if client, ok = s.clients[update.writer]; !ok {
		c := Client{writer: update.writer}
		client = &c
		s.clients[update.writer] = client
	}

	client.updateTime = time.Now()

	for _, r := range update.requests {
		var p *plugin.Plugin
		var err error
		if p, err = plugin.Get(r.Key); err != nil {
			log.Warningf("cannot monitor metric \"%s\": %s", r.Key, err.Error())
		}
		switch p.Impl.(type) {
		case plugin.Collector:
			if !p.Active {
				log.Debugf("Start collector task for plugin %s", p.Impl.Name())
				/*
					task := &CollectorTask{
						Task: Task{
							plugin:    plugin,
							created:   s.updateTime,
							scheduled: GetItemNextcheck(item, s.updateTime)}}
					plugin.Enqueue(task)
				*/
			}
		case plugin.Exporter:
			/*
				task := &ExporterTask{
					Task: Task{
						plugin:    plugin,
						created:   s.updateTime,
						scheduled: GetItemNextcheck(item, s.updateTime)},
					item:    item,
					history: s.historyCache}
				plugin.Enqueue(task)
				}*/
		}
		p.Active = true
	}
}

func (s *Scheduler) run() {
	log.Debugf("starting scheduler")
	ticker := time.NewTicker(time.Second)
run:
	for {
		select {
		case <-ticker.C:
			// TODO: process queue
		case v := <-s.input:
			if v == nil {
				break run
			}
			switch v.(type) {
			case *UpdateRequest:
				s.processUpdateRequest(v.(*UpdateRequest))
			}
		}
	}
	close(s.input)
	log.Debugf("scheduler has been stopped")
	monitor.Unregister()
}

func (s *Scheduler) Start() {
	s.clients = make(map[plugin.ResultWriter]*Client)
	s.input = make(chan interface{}, 10)
	monitor.Register()
	go s.run()
}

func (s *Scheduler) Stop() {
	s.input <- nil
}

func (s *Scheduler) Update(writer plugin.ResultWriter, requests []*plugin.Request) {
	r := UpdateRequest{writer: writer, requests: requests}
	s.input <- &r
}
