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

package serverlistener

import (
	"errors"
	"fmt"
	"net"
	"time"
	"zabbix/internal/agent"
	"zabbix/internal/agent/scheduler"
	"zabbix/internal/monitor"
	"zabbix/pkg/log"
	"zabbix/pkg/zbxcomms"
)

type ServerListener struct {
	listener  *zbxcomms.Listener
	scheduler scheduler.Scheduler
}

func (sl *ServerListener) processRequest(conn *zbxcomms.Connection, data []byte) (err error) {
	return errors.New("json requests are not yet supported")
}

func (sl *ServerListener) processConnection(conn *zbxcomms.Connection) (err error) {
	defer func() {
		if err != nil {
			conn.Close()
		}
	}()

	var data []byte
	if data, err = conn.Read(time.Second * time.Duration(agent.Options.Timeout)); err != nil {
		return err
	}

	if len(data) == 0 {
		err = fmt.Errorf("received empty data from '%s'", conn.RemoteIP())
		return
	}

	if data[0] == '{' {
		return sl.processRequest(conn, data)
	}

	log.Debugf("received passive check request: '%s' from '%s'", string(data), conn.RemoteIP())
	response := passiveCheck{conn: &passiveConnection{conn: conn}, scheduler: sl.scheduler}
	go response.handleCheck(data)

	return nil
}

func (sl *ServerListener) run() {
	defer log.PanicHook()
	log.Debugf("starting listener")

	for {
		conn, err := sl.listener.Accept()
		if err == nil {
			if err := sl.processConnection(conn); err != nil {
				log.Warningf("cannot process incoming connection: %s", err.Error())
			}
		} else {
			if nerr, ok := err.(net.Error); ok && nerr.Temporary() {
				log.Errf("cannot accept incoming connection: %s", err.Error())
				continue
			}
			break
		}
	}

	log.Debugf("listener has been stopped")
	monitor.Unregister()

}

func New(s scheduler.Scheduler) (sl *ServerListener) {
	sl = &ServerListener{scheduler: s}
	return
}

func (sl *ServerListener) Start() (err error) {
	if sl.listener, err = zbxcomms.Listen(fmt.Sprintf(":%d", agent.Options.ListenPort)); err != nil {
		return err
	}
	monitor.Register()
	go sl.run()
	return
}

func (sl *ServerListener) Stop() {
	sl.listener.Close()
}
