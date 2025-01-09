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

package memory

import (
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

var impl Plugin

// Plugin -
type Plugin struct {
	plugin.Base
}

func init() {
	err := plugin.RegisterMetrics(&impl, "Memory",
		"vm.memory.size", "Returns memory size in bytes or in percentage from total.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if len(params) > 1 {
		return nil, zbxerr.ErrorTooManyParameters
	}

	switch key {
	case "vm.memory.size":
		var mode string
		if len(params) > 0 {
			mode = params[0]
		}

		return p.exportVMMemorySize(mode)
	default:
		return nil, plugin.UnsupportedMetricError
	}
}
