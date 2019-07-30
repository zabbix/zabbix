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
	"zabbix/internal/agent/scheduler"
	"zabbix/internal/plugin"
	"zabbix/pkg/log"
)

type passiveCheck struct {
	conn      *passiveConnection
	scheduler scheduler.Scheduler
	results   chan *plugin.Result
}

func (pc *passiveCheck) Write(r *plugin.Result) {
	pc.results <- r
}

func (pc *passiveCheck) formatError(msg string) (data []byte) {
	const notsupported = "ZBX_NOTSUPPORTED"

	data = make([]byte, len(notsupported)+len(msg)+1)
	copy(data, notsupported)
	copy(data[len(notsupported)+1:], msg)
	return
}

func (pc *passiveCheck) handleCheck(data []byte) {
	pc.results = make(chan *plugin.Result)
	pc.scheduler.UpdateTasks(0, pc, []*plugin.Request{&plugin.Request{Key: string(data)}})
	var response []byte
	select {
	case r := <-pc.results:
		if r.Error == nil {
			response = []byte(*r.Value)
		} else {
			response = pc.formatError(r.Error.Error())
		}
	case <-time.After(time.Second * time.Duration(Options.Timeout)):
		response = pc.formatError("timeout occurred")
	}
	if _, err := pc.conn.Write(response); err != nil {
		log.Warningf("could not send response to server")
	}
	close(pc.results)
}
