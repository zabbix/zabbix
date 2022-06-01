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
	"encoding/binary"
	"unsafe"

	"git.zabbix.com/ap/plugin-support/plugin"
	"zabbix.com/pkg/win32"
)

func exportSystemTcpListen(port uint16) (result interface{}, err error) {
	var tcpTable *win32.MIB_TCPTABLE
	var sizeIn, sizeOut uint32
	if sizeOut, err = win32.GetTcpTable(nil, 0, false); err != nil {
		return
	}
	if sizeOut == 0 {
		return
	}
	for sizeOut > sizeIn {
		sizeIn = sizeOut
		buf := make([]byte, sizeIn)
		tcpTable = (*win32.MIB_TCPTABLE)(unsafe.Pointer(&buf[0]))
		if sizeOut, err = win32.GetTcpTable(tcpTable, sizeIn, false); err != nil {
			return
		}
	}

	var nport uint16
	binary.BigEndian.PutUint16((*[2]byte)(unsafe.Pointer(&nport))[:2], port)
	rows := (*win32.RGMIB_TCPROW)(unsafe.Pointer(&tcpTable.Table[0]))[:tcpTable.NumEntries:tcpTable.NumEntries]
	for _, row := range rows {
		if row.State == win32.MIB_TCP_STATE_LISTEN && uint16(row.LocalPort) == nport {
			return 1, nil
		}
	}
	return 0, nil
}

func init() {
	plugin.RegisterMetrics(&impl, "TCP",
		"net.tcp.listen", "Checks if this TCP port is in LISTEN state.",
		"net.tcp.port", "Checks if it is possible to make TCP connection to specified port.",
		"net.tcp.service", "Checks if service is running and accepting TCP connections.",
		"net.tcp.service.perf", "Checks performance of TCP service.",
		"net.tcp.socket.count", "Returns number of TCP sockets that match parameters.")
}
