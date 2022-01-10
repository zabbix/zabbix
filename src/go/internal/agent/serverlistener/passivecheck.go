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

package serverlistener

import (
	"time"

	"zabbix.com/internal/agent"
	"zabbix.com/internal/agent/scheduler"
	"zabbix.com/pkg/log"
)

const notsupported = "ZBX_NOTSUPPORTED"

type passiveCheck struct {
	conn      *passiveConnection
	scheduler scheduler.Scheduler
}

func (pc *passiveCheck) formatError(msg string) (data []byte) {
	data = make([]byte, len(notsupported)+len(msg)+1)
	copy(data, notsupported)
	copy(data[len(notsupported)+1:], msg)
	return
}

func (pc *passiveCheck) handleCheck(data []byte) {
	// the timeout is one minute to allow agent connections (with max timeout of 30s) safely execute
	const timeoutForSinglePassiveChecks = time.Minute
	// direct passive check timeout is handled by the scheduler
	s, err := pc.scheduler.PerformTask(string(data), timeoutForSinglePassiveChecks, agent.PassiveChecksClientID)

	if err != nil {
		log.Debugf("sending passive check response: %s: '%s' to '%s'", notsupported, err.Error(), pc.conn.Address())
		_, err = pc.conn.Write(pc.formatError(err.Error()))
	} else {
		log.Debugf("sending passive check response: '%s' to '%s'", s, pc.conn.Address())
		_, err = pc.conn.Write([]byte(s))
	}

	if err != nil {
		log.Debugf("could not send response to server '%s': %s", pc.conn.Address(), err.Error())
	}
}
