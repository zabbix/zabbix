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

package wmi

import (
	"encoding/json"
	"errors"

	"git.zabbix.com/ap/plugin-support/plugin"
	"zabbix.com/pkg/wmi"
)

// Plugin -
type Plugin struct {
	plugin.Base
}

var impl Plugin

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if len(params) != 2 {
		return nil, errors.New("Invalid number of parameters.")
	}
	switch key {
	case "wmi.get":
		return wmi.QueryValue(params[0], params[1])
	case "wmi.getall":
		m, err := wmi.QueryTable(params[0], params[1])
		if err != nil {
			return nil, err
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

func init() {
	plugin.RegisterMetrics(&impl, "Wmi",
		"wmi.get", "Execute WMI query and return the first selected object.",
		"wmi.getall", "Execute WMI query and return the whole response converted in JSON format.",
	)
}
