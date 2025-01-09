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

package serverlistener

import (
	"golang.zabbix.com/agent2/pkg/zbxcomms"
)

type passiveConnection struct {
	conn *zbxcomms.Connection
}

func (pc *passiveConnection) Write(data []byte) (n int, err error) {
	if err = pc.conn.Write(data); err != nil {
		n = len(data)
	}
	pc.conn.Close()
	return
}

func (pc *passiveConnection) Address() string {
	return pc.conn.RemoteIP()
}
