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
	"encoding/binary"
	"unsafe"

	"golang.zabbix.com/agent2/pkg/win32"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

func init() {
	err := plugin.RegisterMetrics(
		&impl, "TCP",
		"net.tcp.listen", "Checks if this TCP port is in LISTEN state.",
		"net.tcp.port", "Checks if it is possible to make TCP connection to specified port.",
		"net.tcp.service", "Checks if service is running and accepting TCP connections.",
		"net.tcp.service.perf", "Checks performance of TCP service.",
		"net.tcp.socket.count", "Returns number of TCP sockets that match parameters.",
	)
	if err != nil {
		panic(zbxerr.New("failed to register metrics").Wrap(err))
	}

	impl.SetHandleTimeout(true)
}

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
