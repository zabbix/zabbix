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

package vmemory

import (
	"golang.zabbix.com/agent2/pkg/win32"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

const percent = 100

var impl Plugin

// Plugin -
type Plugin struct {
	plugin.Base
}

func init() {
	err := plugin.RegisterMetrics(
		&impl, "VMemory",
		"vm.vmemory.size", "Returns virtual memory size in bytes or in percentage.",
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
	case "vm.vmemory.size":
		var mode string
		if len(params) > 0 {
			mode = params[0]
		}

		return p.exportVMVMemorySize(mode)
	default:
		return nil, plugin.UnsupportedMetricError
	}
}

func (p *Plugin) exportVMVMemorySize(mode string) (result interface{}, err error) {
	mem, err := win32.GlobalMemoryStatusEx()
	if err != nil {
		return nil, err
	}
	switch mode {
	case "", "total":
		return mem.TotalPageFile, nil
	case "available":
		return mem.AvailPageFile, nil
	case "used":
		return mem.TotalPageFile - mem.AvailPageFile, nil
	case "pused":
		return float64(mem.TotalPageFile-mem.AvailPageFile) / float64(mem.TotalPageFile) * percent, nil
	case "pavailable":
		return float64(mem.AvailPageFile) / float64(mem.TotalPageFile) * percent, nil
	default:
		return nil, zbxerr.ErrorInvalidParams
	}
}
