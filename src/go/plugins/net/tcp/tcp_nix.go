//go:build !windows
// +build !windows

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

package tcpudp

import (
	"errors"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

func init() {
	err := plugin.RegisterMetrics(
		&impl, "TCP",
		"net.tcp.port", "Checks if it is possible to make TCP connection to specified port.",
		"net.tcp.service", "Checks if service is running and accepting TCP connections.",
		"net.tcp.service.perf", "Checks performance of TCP service.",
		"net.tcp.socket.count", "Returns number of TCP sockets that match parameters.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}

	impl.SetHandleTimeout(true)
}

func exportSystemTcpListen(port uint16) (result interface{}, err error) {
	return nil, errors.New("Not supported.")
}
