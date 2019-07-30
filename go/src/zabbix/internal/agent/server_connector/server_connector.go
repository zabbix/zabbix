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
** GNU General Public License for more detailm.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

package server_connector

import (
	"encoding/json"
	"time"
	"zabbix/internal/agent"
	"zabbix/internal/agent/scheduler"
	"zabbix/internal/monitor"
	"zabbix/internal/plugin"
	"zabbix/pkg/comms"
	"zabbix/pkg/log"
)

type ServerConnector struct {
	input       chan interface{}
	Address     string
	lastError   error
	ResultCache *agent.ResultCache
	TaskManager *scheduler.Manager
}

func (s *ServerConnector) init() {
	s.input = make(chan interface{}, 10)
}

func (s *ServerConnector) getActiveChecks() ([]byte, error) {
	var c comms.ZbxConnection

	err := c.Open(s.Address, time.Second*time.Duration(agent.Options.Timeout))
	if err != nil {
		return nil, err
	}

	defer c.Close()

	err = c.WriteString("{\"request\":\"active checks\",\"host\":\""+agent.Options.Hostname+"\"}", 0)
	if err != nil {
		return nil, err
	}

	b, err := c.Read(0)
	if err != nil {
		return nil, err
	}

	return b, nil
}

type response struct {
	Response string            `json:"response"`
	Info     string            `json:"info"`
	Data     []*plugin.Request `json:"data"`
}

func (s *ServerConnector) refreshActiveChecks() {
	js, err := s.getActiveChecks()

	if err != nil {
		if s.lastError == nil || err.Error() != s.lastError.Error() {
			log.Warningf("active check configuration update from [%s] started to fail (%s)", s.Address, err)
			s.lastError = err

		}
		return
	}
	s.lastError = nil

	log.Debugf("got [%s]", string(js))

	var r response

	err = json.Unmarshal(js, &r)
	if err != nil {
		log.Errf("cannot parse list of active checks from [%s]: %s", s.Address, err)
		return
	}

	if r.Response != "success" {
		if len(r.Info) != 0 {
			log.Errf("no active checks on server [%s]: %s", s.Address, r.Info)
		} else {
			log.Errf("no active checks on server")
		}

		return
	}

	if nil == r.Data {
		log.Errf("cannot parse list of active checks: data array is missing")
		return
	}

	s.TaskManager.UpdateTasks(s.ResultCache, r.Data)
}

func (s *ServerConnector) run() {

	defer log.PanicHook()
	log.Debugf("starting Server connector")
	s.refreshActiveChecks()
	ticker := time.NewTicker(time.Second * time.Duration(agent.Options.RefreshActiveChecks))
run:
	for {
		select {
		case <-ticker.C:
			s.refreshActiveChecks()
		case <-s.input:
			break run
		}
	}
	close(s.input)
	log.Debugf("Server connector has been stopped")
	monitor.Unregister()
}

func (s *ServerConnector) Start() {
	s.init()
	monitor.Register()
	go s.run()
}

func (s *ServerConnector) Stop() {
	s.input <- nil
}
