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

package zabbixasync

import (
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/zbxlib"
)

// Plugin -
type Plugin struct {
	plugin.Base
}

var impl Plugin

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	return zbxlib.ExecuteCheck(key, params)
}

func init() {
	plugin.RegisterMetrics(&impl, "ZabbixAsync",
		"system.localtime", "Returns system local time.",
		"system.boottime", "Returns system boot time.",
		"net.tcp.listen", "Checks if this TCP port is in LISTEN state.",
		"net.udp.listen", "Checks if this UDP port is in LISTEN state.",
		"sensor", "Hardware sensor reading.",
		"system.cpu.load", "CPU load.",
		"system.cpu.switches", "Count of context switches.",
		"system.cpu.intr", "Device interrupts.",
		"system.hw.cpu", "CPU information.",
		"system.hw.macaddr", "Listing of MAC addresses.",
		"system.sw.os", "Operating system information.",
		"system.swap.in", "Swap in (from device into memory) statistics.",
		"system.swap.out", "Swap out (from memory onto device) statistics.",
		"vfs.file.md5sum", "MD5 checksum of file.",
		"vfs.file.regmatch", "Find string in a file.",
		"vfs.fs.discovery", "List of mounted filesystems. Used for low-level discovery.")
}
