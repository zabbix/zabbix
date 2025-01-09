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

package wmi

import (
	"encoding/json"
	"errors"

	"golang.zabbix.com/agent2/pkg/wmi"
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
		&impl, "Wmi",
		"wmi.get", "Execute WMI query and return the first selected object.",
		"wmi.getall", "Execute WMI query and return the whole response converted in JSON format.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

// wmiFmtAdapter returns value adapted to the format of classic C Zabbix agent for compatibility reasons.
// Boolean values should be converted to "True" and "False" strings starting with capital letter.
func wmiFmtAdapter(value interface{}) interface{} {
	switch v := value.(type) {
	case bool:
		if v {
			return "True"
		} else {
			return "False"
		}
	default:
		return value
	}
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if len(params) != 2 {
		return nil, errors.New("Invalid number of parameters.")
	}
	switch key {
	case "wmi.get":
		value, err := wmi.QueryValue(params[0], params[1])
		if err != nil {
			return nil, err
		}

		return wmiFmtAdapter(value), err
	case "wmi.getall":
		m, err := wmi.QueryTable(params[0], params[1])
		if err != nil {
			return nil, err
		}
		for i := range m {
			for k := range m[i] {
				m[i][k] = wmiFmtAdapter(m[i][k])
			}
		}
		b, err := json.Marshal(&m)
		if err != nil {
			return nil, err
		}

		return string(b), nil
	default:
		return nil, plugin.UnsupportedMetricError
	}
}
