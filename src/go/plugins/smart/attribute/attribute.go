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

package attribute

import (
	"encoding/json"
	"fmt"

	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/plugin"
	"zabbix.com/plugins/smart"
)

// Options -
type Options struct {
	Timeout int `conf:"optional,range=1:30"`
}

// Plugin -
type Plugin struct {
	plugin.Base
	options Options
}

var impl Plugin

// Configure -
func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	if err := conf.Unmarshal(options, &p.options); err != nil {
		p.Warningf("cannot unmarshal configuration options: %s", err)
	}
	if p.options.Timeout == 0 {
		p.options.Timeout = global.Timeout
	}
}

// Validate -
func (p *Plugin) Validate(options interface{}) error {
	var o Options
	return conf.Unmarshal(options, &o)
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if err = smart.CheckVerson(&p.Base, p.options.Timeout); err != nil {
		return
	}

	switch key {
	case "smart.attribute.discovery":
		var out []smart.Attribute
		parsedDevices, err := smart.GetParsedDevices(&p.Base, p.options.Timeout)
		if err != nil {
			return nil, err
		}

		for _, dev := range parsedDevices {
			var t string
			if dev.RotationRate == 0 {
				t = "ssd"
			} else {
				t = "hdd"
			}

			for _, attr := range dev.SmartAttributes.Table {
				out = append(out, smart.Attribute{Name: smart.CutPrefix(dev.Info.Name), DeviceType: t, ID: attr.ID, Attrname: attr.Attrname, Thresh: attr.Thresh})
			}
		}

		jsonArray, err := json.Marshal(out)
		if err != nil {
			return nil, err
		}

		return string(jsonArray), nil
	}

	return nil, fmt.Errorf("Incorrect key.")
}

func init() {
	plugin.RegisterMetrics(&impl, "Attribute",
		"smart.attribute.discovery", "Returns JSON array of smart device attributes, usage: smart.attribute.discovery.")
}
