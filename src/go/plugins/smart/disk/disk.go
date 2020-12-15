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

	"zabbix.com/pkg/plugin"
	"zabbix.com/plugins/smart"
)

// Plugin -
type Plugin struct {
	plugin.Base
}

var impl Plugin

//Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "smart.disk.discovery":
		var out []smart.Device
		parsedDevices, err := smart.GetParsedDevices(&p.Base)
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
			out = append(out, smart.Device{Name: dev.Info.Name, DeviceType: t, Model: dev.ModelName,
				SerialNumber: dev.SerialNumber})
		}

		jsonArray, err := json.Marshal(out)
		if err != nil {
			return nil, err
		}

		return string(jsonArray), nil
	case "smart.disk.get":
		deviceJsons, err := smart.GetDeviceJsons(&p.Base)
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
		rate, ok := b["rotation_rate"]
		if ok {
			if rate == 0 {
				b["disk_type"] = "ssd"
			} else {
				b["disk_type"] = "hdd"
			}
		} else {
			b["disk_type"] = "unknown"
		}

		out = append(out, b)
	}

	return out
}

func init() {
	plugin.RegisterMetrics(&impl, "Disk",
		"smart.disk.discovery", "Returns JSON array of smart devices, usage: smart.disk.discovery.",
		"smart.disk.get", "Returns JSON data of smart device, usage: smart.disk.get[name].")
}
