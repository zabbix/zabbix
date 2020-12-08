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

	"github.com/pkg/errors"
	"zabbix.com/pkg/plugin"
)

var errRaid = errors.New("/dev/sda: requires option '-d cciss,N'")

// Plugin -
type Plugin struct {
	plugin.Base
}

type devices struct {
	Devices []deviceInfo `json:"devices"`
}

type deviceInfo struct {
	Name       string `json:"name"`
	DeviceType string `json:"type"`
}

type deviceParser struct {
	ModelName    string        `json:"model_name"`
	SerialNumber string        `json:"serial_number"`
	Info         deviceInfo    `json:"device"`
	Smartctl     smartctlField `json:"smartctl"`
}

type smartctlField struct {
	Messages []message `json:"messages"`
}

type message struct {
	Str      string `json:"string"`
	Severity string `json:"severity"`
}

type device struct {
	Name         string `json:"{#NAME}"`
	DeviceType   string `json:"{#TYPE}"`
	Model        string `json:"{#MODEL}"`
	SerialNumber string `json:"{#SN}"`
}

var impl Plugin

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	var out []device
	switch key {
	case "smart.disk.discovery":
		devices, err := getDevices()
		if err != nil {
			return nil, err
		}

		for _, dev := range devices {
			deviceJSON, err := executeSmartctl(fmt.Sprintf("-a %s -json", dev.Name))
			if err != nil {
				return nil, fmt.Errorf("Failed to execute smartctl: %s", err.Error())
			}

			var dp deviceParser
			if err = json.Unmarshal([]byte(deviceJSON), &dp); err != nil {
				return nil, fmt.Errorf("Failed to unmarshal smartctl response json: %s", err.Error())
			}

			if err = checkErr(dp); err != nil {
				if err != errRaid {
					return nil, fmt.Errorf("Failed to get disk data from smartctl: %s", err.Error())
				}
				var n int
				n, err = deviceCount(dev.Name)
				if err != nil {
					return nil, fmt.Errorf("Failed to get raid device count: %s", err.Error())
				}

				raids, err := getRaidDisks(dev.Name, n-2)
				if err != nil {
					return nil, fmt.Errorf("Failed to get raid disk data from smartctl: %s", err.Error())
				}

				out = append(out, raids...)
				continue
			}

			out = append(out, device{dp.Info.Name, dp.Info.DeviceType, dp.ModelName, dp.SerialNumber})
		}
	case "smart.disk.get":
		if len(params) != 1 {
			return nil, errors.New("Invalid first parameter.")
		}
		name := params[0]

		deviceJSON, err := executeSmartctl(fmt.Sprintf("-a %s -json", name))
		if err != nil {
			return nil, err
		}
		var dp deviceParser
		if err = json.Unmarshal([]byte(deviceJSON), &dp); err != nil {
			return nil, err
		}

		if err = checkErr(dp); err != nil {
			if err != errRaid {
				return nil, fmt.Errorf("Failed to get disk data from smartctl: %s", err.Error())
			}
		}

		return deviceJSON, nil

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
	plugin.RegisterMetrics(&impl, "Disk",
		"smart.disk.discovery", "Returns JSON array of smart devices, usage: smart.disk.discovery.",
		"smart.disk.get", "Returns JSON data of smart device, usage: smart.disk.get[name].")
}

func getRaidDisks(name string, count int) ([]device, error) {
	var out []device
	for i := 0; i <= count; i++ {
		fullName := fmt.Sprintf("%s -d cciss,%d", name, i)
		deviceJSON, err := executeSmartctl(fmt.Sprintf("-a %s -j ", fullName))
		if err != nil {
			return nil, fmt.Errorf("Failed to get RAID disk data from smartctl: %s", err.Error())
		}

		var dp deviceParser
		if err = json.Unmarshal([]byte(deviceJSON), &dp); err != nil {
			return nil, fmt.Errorf("Failed to unmarshal smartctl RAID response json: %s", err.Error())
		}

		err = checkErr(dp)
		if err != nil {
			return nil, fmt.Errorf("Failed to get disk data from smartctl: %s", err.Error())
		}

		out = append(out, device{fullName, dp.Info.DeviceType, dp.ModelName, dp.SerialNumber})
	}

	return out, nil
}

func checkErr(dp deviceParser) error {
	for _, m := range dp.Smartctl.Messages {
		if m.Severity == "error" {
			if m.Str == errRaid.Error() {
				return errRaid
			}

			return errors.New(m.Str)
		}
	}
	return nil
}

func getDevices() ([]deviceInfo, error) {
	raidDev, err := scanDevices("--scan -d sat -j")
	if err != nil {
		return nil, fmt.Errorf("Failed to scan for sat devices: %s", err)
	}

	dev, err := scanDevices("--scan -j")
	if err != nil {
		return nil, fmt.Errorf("Failed to scan for devices: %s", err)
	}
	var out []deviceInfo
	out = append(out, raidDev...)
raid:
	for _, d := range dev {
		for _, rd := range raidDev {
			if d.Name == rd.Name {
				continue raid
			}
		}

		out = append(out, d)
	}

	return out, nil
}

func scanDevices(args string) ([]deviceInfo, error) {
	var devices devices
	devicesJSON, err := executeSmartctl(args)
	if err != nil {
		return nil, err
	}

	if err = json.Unmarshal([]byte(devicesJSON), &devices); err != nil {
		return nil, err
	}

	return devices.Devices, nil
}
