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

package smart

import (
	"encoding/json"
	"fmt"
	"path/filepath"
	"time"

	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/zbxcmd"
)

// Plugin -
type Plugin struct {
	plugin.Base
}

type deviceInfo struct {
	Name       string `json:"name"`
	DeviceType string `json:"type"`
}

type deviceParser struct {
	ModelName    string     `json:"model_name"`
	SerialNumber string     `json:"serial_number"`
	Info         deviceInfo `json:"device"`
}

type device struct {
	Name         string `json:"#NAME"`
	DeviceType   string `json:"#TYPE"`
	Model        string `json:"#MODEL"`
	SerialNumber string `json:"#SN"`
}

var impl Plugin

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	var out []device

	switch key {
	case "smart.discovery":
		devices, err := ScanDevices()
		if err != nil {
			return nil, err
		}

		for _, dev := range devices {
			deviceJSON, err := zbxcmd.Execute(fmt.Sprintf("smartctl -a %s -json", dev), time.Second*time.Duration(3*time.Second))
			if err != nil {
				return nil, err
			}
			var dp deviceParser
			if err = json.Unmarshal([]byte(deviceJSON), &dp); err != nil {
				return nil, err
			}

			out = append(out, device{dp.Info.Name, dp.Info.DeviceType, dp.ModelName, dp.SerialNumber})
		}

	default:
		return nil, fmt.Errorf("Incorrect key.")
	}

	jsonArray, err := json.Marshal(out)
	if err != nil {
		return nil, err
	}
	return string(jsonArray), nil
}

func init() {
	plugin.RegisterMetrics(&impl, "Smart",
		"smart.discovery", "Returns JSON array of smart devices, usage: smart.discovery.")
}

func ScanDevices() ([]string, error) {
	var devices []string
	files, err := filepath.Glob("/dev/sd*[^0-9]")
	if err != nil {
		return nil, err
	}

	for _, file := range files {
		devices = append(devices, file)
	}

	return devices, nil
}
