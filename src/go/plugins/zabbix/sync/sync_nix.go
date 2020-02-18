// +build !windows

/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

package zabbixsync

func getMetrics() []string {
	return []string{
		"net.dns", "Checks if DNS service is up.",
		"net.dns.record", "Performs DNS query.",
		"proc.mem", "Memory used by process in bytes.",
		"proc.num", "The number of processes.",
		"web.page.get", "Get content of web page.",
		"web.page.perf", "Loading time of full web page (in seconds).",
		"web.page.regexp", "Find string on a web page.",
		"system.hw.chassis", "Chassis information.",
		"system.hw.devices", "Listing of PCI or USB devices.",
		"system.sw.packages", "Listing of installed packages.",
		"net.tcp.service", "Checks if service is running and accepting TCP connections.",
		"net.tcp.service.perf", "Checks performance of TCP service.",
		"net.udp.service", "Checks if service is running and responding to UDP requests.",
		"net.udp.service.perf", "Checks performance of UDP service.",
		"system.users.num", "Number of users logged in.",
		"system.swap.size", "Swap space size in bytes or in percentage from total.",
		"vfs.dir.count", "Directory entry count.",
		"vfs.dir.size", "Directory size (in bytes).",
		"vfs.fs.inode", "Number or percentage of inodes.",
		"vfs.fs.size", "Disk space in bytes or in percentage from total.",
		"vm.memory.size", "Memory size in bytes or in percentage from total.",
	}
}
