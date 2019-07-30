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
	"errors"
	"fmt"
	"net"
	"time"
	"zabbix/internal/agent/scheduler"
	"zabbix/internal/monitor"
	"zabbix/pkg/comms"
	"zabbix/pkg/log"
)

type ServerListener struct {
	input     chan interface{}
	listener  *comms.ZbxListener
	Scheduler scheduler.Scheduler
}

func (l *ServerListener) processRequest(data []byte) (err error) {
	return errors.New("json requests are not yet supported")
}

func (l *ServerListener) processConnection(conn *comms.ZbxConnection) (err error) {
	var data []byte
	if data, err = conn.Read(time.Second * time.Duration(Options.Timeout)); err != nil {
		return err
	}

	if data[0] == '{' {
		return l.processRequest(data)
	}

	log.Debugf("recived passive check request: '%s'", string(data))
	response := passiveCheck{conn: &passiveConnection{conn: conn}, scheduler: l.Scheduler}
	go response.handleCheck(data)

	return nil
}

func (l *ServerListener) run() {
	defer log.PanicHook()
	log.Debugf("starting listener")
run:
	for {
		select {
		case v := <-l.input:
			if v == nil {
				break run
			}
		default:
			conn, err := l.listener.Accept(1e9)
			if err == nil {
				if err = l.processConnection(conn); err != nil {
					log.Warningf("cannot process incoming connection: %s", err.Error())
				}
			} else {
				if neterr, ok := err.(net.Error); ok && neterr.Timeout() {
					continue
				}
				log.Errf("cannot accept incoming connection: %s", err.Error())
			}
		}
	}

	close(l.input)
	log.Debugf("listener has been stopped")
	monitor.Unregister()

}

func (l *ServerListener) Start() (err error) {
	if l.listener, err = comms.Listen(fmt.Sprintf(":%d", Options.ListenPort)); err != nil {
		return err
	}
	monitor.Register()
	go l.run()
	return
}

func (l *ServerListener) Stop() {
	l.input <- nil
}
