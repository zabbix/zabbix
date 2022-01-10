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

package kernel

import (
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/std"
	"zabbix.com/pkg/zbxerr"
)

// Plugin -
type Plugin struct {
	plugin.Base
}

var impl Plugin
var stdOs std.Os

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if len(params) > 0 {
		return nil, zbxerr.ErrorTooManyParameters
	}

	switch key {
	case "kernel.maxproc", "kernel.maxfiles", "kernel.openfiles":
		return getFirstNum(key)
	default:
		/* SHOULD_NEVER_HAPPEN */
		return 0, plugin.UnsupportedMetricError
	}
}

func init() {
	stdOs = std.NewOs()
	plugin.RegisterMetrics(&impl, "Kernel",
		"kernel.maxproc", "Returns maximum number of processes supported by OS.",
		"kernel.maxfiles", "Returns maximum number of opened files supported by OS.",
		"kernel.openfiles", "Returns number of currently open file descriptors.")
}
