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

package zabbixsync

import (
	"zabbix/internal/plugin"
	"zabbix/pkg/zbxlib"
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
	plugin.RegisterMetric(&impl, "zabbixsync", "net.dns", "Checks if DNS service is up")
	plugin.RegisterMetric(&impl, "zabbixsync", "net.dns.record", "Performs DNS query")
	plugin.RegisterMetric(&impl, "zabbixsync", "proc.mem", "Memory used by process in bytes")
	plugin.RegisterMetric(&impl, "zabbixsync", "proc.num", "The number of processes")
	plugin.RegisterMetric(&impl, "zabbixsync", "web.page.get", "Get content of web page")
	plugin.RegisterMetric(&impl, "zabbixsync", "web.page.perf", "Loading time of full web page (in seconds)")
	plugin.RegisterMetric(&impl, "zabbixsync", "web.page.regexp", "Find string on a web page")
	plugin.RegisterMetric(&impl, "zabbixsync", "system.hw.chassis", "Chassis information")
	plugin.RegisterMetric(&impl, "zabbixsync", "system.hw.devices", "Listing of PCI or USB devices")
	plugin.RegisterMetric(&impl, "zabbixsync", "system.sw.packages", "Listing of installed packages")
	plugin.RegisterMetric(&impl, "zabbixsync", "net.tcp.port", "Checks if it is possible to make TCP connection to specified port")
	plugin.RegisterMetric(&impl, "zabbixsync", "net.tcp.service", "Checks if service is running and accepting TCP connections")
	plugin.RegisterMetric(&impl, "zabbixsync", "net.tcp.service.perf", "Checks performance of TCP service")
	plugin.RegisterMetric(&impl, "zabbixsync", "net.udp.service", "Checks if service is running and responding to UDP requests")
	plugin.RegisterMetric(&impl, "zabbixsync", "net.udp.service.perf", "Checks performance of UDP service")
	plugin.RegisterMetric(&impl, "zabbixsync", "system.users.num", "Number of users logged in")
	plugin.RegisterMetric(&impl, "zabbixsync", "vfs.dir.count", "Directory entry count")
	plugin.RegisterMetric(&impl, "zabbixsync", "vfs.dir.size", "Directory size (in bytes)")
	plugin.RegisterMetric(&impl, "zabbixsync", "vfs.file.size", "File size (in bytes)")
	plugin.RegisterMetric(&impl, "zabbixsync", "vfs.file.time", "File time information")
	plugin.RegisterMetric(&impl, "zabbixsync", "vfs.fs.inode", "Number or percentage of inodes")
	plugin.RegisterMetric(&impl, "zabbixsync", "vfs.fs.size", "Disk space in bytes or in percentage from total")
	impl.SetCapacity(1)
}
