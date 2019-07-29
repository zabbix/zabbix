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
	"time"
	"zabbix/internal/agent"
	"zabbix/internal/monitor"
	"zabbix/pkg/comms"
	"zabbix/pkg/log"
)

type ServerConnector struct {
	input   chan interface{}
	Address string
}

func (s *ServerConnector) init() {
	s.input = make(chan interface{}, 10)
}

func (s *ServerConnector) refreshActiveChecks() ([]byte, error) {
	var c comms.ZbxConnection

	err := c.Open(s.Address, time.Second*time.Duration(agent.Options.Timeout))
	if err != nil {
		return nil, err
	}

	err = c.WriteString("{\"request\":\"active checks\",\"host\":\""+agent.Options.Hostname+"\"}", 0)
	if err != nil {
		return nil, err
	}

	b, err := c.Read(0)
	if err != nil {
		return nil, err
	}

	err = c.Close()
	if err != nil {
		return nil, err
	}

	return b, nil
}

var lastError error

func (s *ServerConnector) handleActiveChecks() {
	b, err := s.refreshActiveChecks()
	if err != nil {
		if lastError == nil {
			log.Warningf("active check configuration update from [%s] started to fail (%s)", s.Address, err)
			lastError = err
		}
	} else {
		log.Debugf("got [%s]", string(b))
		lastError = nil
	}
}

func (s *ServerConnector) run() {
	defer log.PanicHook()
	log.Debugf("starting Server connector")
	ticker := time.NewTicker(time.Second * time.Duration(agent.Options.RefreshActiveChecks))
	s.handleActiveChecks()
run:
	for {
		select {
		case <-ticker.C:
			s.handleActiveChecks()
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
