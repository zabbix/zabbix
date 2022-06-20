//go:build !windows
// +build !windows

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

package tcpudp

import (
	"errors"

	"git.zabbix.com/ap/plugin-support/plugin"
)

func exportSystemTcpListen(port uint16) (result interface{}, err error) {
	return nil, errors.New("Not supported.")
}

func init() {
	plugin.RegisterMetrics(&impl, "TCP",
		"net.tcp.port", "Checks if it is possible to make TCP connection to specified port.",
		"net.tcp.service", "Checks if service is running and accepting TCP connections.",
		"net.tcp.service.perf", "Checks performance of TCP service.",
		"net.tcp.socket.count", "Returns number of TCP sockets that match parameters.")
}
