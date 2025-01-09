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

package uname

import (
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

var impl Plugin

// Plugin -
type Plugin struct {
	plugin.Base
}

func init() {
	err := plugin.RegisterMetrics(
		&impl, "Uname",
		"system.uname", "Returns system uname.",
		"system.hostname", "Returns system host name.",
		"system.sw.arch", "Software architecture information.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "system.uname":
		return getUname(params)
	case "system.hostname":
		return getHostname(params)
	case "system.sw.arch":
		return getSwArch(params)
	default:
		return nil, plugin.UnsupportedMetricError
	}
}
