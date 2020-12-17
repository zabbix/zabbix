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

package disk

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

//Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "smart.disk.discovery":
		var out []smart.Device
		parsedDevices, err := smart.GetParsedDevices(&p.Base, p.options.Timeout)
		if err != nil {
			return nil, err
		}

		for _, dev := range parsedDevices {
			out = append(out, smart.Device{Name: dev.Info.Name, DeviceType: getType(dev.Info.DevType, dev.RotationRate),
				Model: dev.ModelName, SerialNumber: dev.SerialNumber})
		}

		jsonArray, err := json.Marshal(out)
		if err != nil {
			return nil, err
		}

		return string(jsonArray), nil
	case "smart.disk.get":
		deviceJsons, err := smart.GetDeviceJsons(&p.Base, p.options.Timeout)
		if err != nil {
			return nil, err
		}

		jsonArray, err := json.Marshal(setDiskType(deviceJsons))
		if err != nil {
			return nil, err
		}

		return string(jsonArray), nil
	}

	return nil, fmt.Errorf("Incorrect key.")
}

func setDiskType(deviceJsons map[string]string) []interface{} {
	var out []interface{}
	for k, v := range deviceJsons {
		b := make(map[string]interface{})
		json.Unmarshal([]byte(v), &b)
		b["disk_name"] = k
		var devType string
		if dev, ok := b["device"]; ok {
			s, ok := dev.(string)
			if ok {
				info := make(map[string]string)
				err := json.Unmarshal([]byte(s), &info)
				if err == nil {
					devType = info["type"]
				}
			}
		}

		rateInt := -1
		if rate, ok := b["rotation_rate"]; ok {
			if r, ok := rate.(int); ok {
				rateInt = r
			}
		}

		b["disk_type"] = getType(devType, rateInt)
		out = append(out, b)
	}

	return out
}

func getType(devType string, rate int) (out string) {
	out = "unknown"
	if devType == "nvme" {
		out = "nvme"
	} else {
		if rate == 0 {
			out = "ssd"
		} else {
			out = "hdd"
		}
	}

	return
}

func init() {
	plugin.RegisterMetrics(&impl, "Disk",
		"smart.disk.discovery", "Returns JSON array of smart devices, usage: smart.disk.discovery.",
		"smart.disk.get", "Returns JSON data of smart device, usage: smart.disk.get[name].")
}
