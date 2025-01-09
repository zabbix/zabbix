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

package zabbixasync

func getMetrics() []string {
	return []string{
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
	}
}
