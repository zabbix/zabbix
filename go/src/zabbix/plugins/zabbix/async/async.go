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
	plugin.RegisterMetric(&impl, "zabbixasync", "system.localtime", "Returns system local time")
	plugin.RegisterMetric(&impl, "zabbixasync", "system.boottime", "Returns system boot time")
	plugin.RegisterMetric(&impl, "zabbixasync", "net.tcp.listen", "Checks if this TCP port is in LISTEN state")
	plugin.RegisterMetric(&impl, "zabbixasync", "net.udp.listen", "Checks if this UDP port is in LISTEN state")
	plugin.RegisterMetric(&impl, "zabbixasync", "sensor", "Hardware sensor reading")
	plugin.RegisterMetric(&impl, "zabbixasync", "system.cpu.load", "CPU load")
	plugin.RegisterMetric(&impl, "zabbixasync", "system.cpu.num", "Number of CPUs")
	plugin.RegisterMetric(&impl, "zabbixasync", "system.cpu.switches", "Count of context switches")
	plugin.RegisterMetric(&impl, "zabbixasync", "system.cpu.intr", "Device interrupts")
	plugin.RegisterMetric(&impl, "zabbixasync", "system.hw.cpu", "CPU information")
	plugin.RegisterMetric(&impl, "zabbixasync", "system.hw.macaddr", "Listing of MAC addresses")
	plugin.RegisterMetric(&impl, "zabbixasync", "system.sw.os", "Operating system information")
	plugin.RegisterMetric(&impl, "zabbixasync", "system.swap.in", "Swap in (from device into memory) statistics")
	plugin.RegisterMetric(&impl, "zabbixasync", "system.swap.out", "Swap out (from memory onto device) statistics")
	plugin.RegisterMetric(&impl, "zabbixasync", "system.swap.size", "Swap space size in bytes or in percentage from total")
}
