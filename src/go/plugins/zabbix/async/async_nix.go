//go:build !windows

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

import (
	"golang.zabbix.com/agent2/pkg/zbxlib"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

//nolint:gochecknoglobals
var impl Plugin

// Plugin structure.
type Plugin struct {
	plugin.Base
}

func init() { //nolint:gochecknoinits // such is current plugin implementation.
	err := plugin.RegisterMetrics(&impl, "ZabbixAsync", getMetrics()...)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

// Export implements exporter interface.
func (*Plugin) Export(key string, params []string, _ plugin.ContextProvider) (any, error) {
	result, err := zbxlib.ExecuteCheck(key, params)
	if err != nil {
		return nil, errs.Wrap(err, "failed to execute check")
	}

	return result, nil
}

func getMetrics() []string {
	return []string{
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
