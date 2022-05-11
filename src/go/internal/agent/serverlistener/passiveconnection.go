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
	"zabbix.com/pkg/zbxcomms"
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
