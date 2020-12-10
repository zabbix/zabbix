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
	ModelName       string          `json:"model_name"`
	SerialNumber    string          `json:"serial_number"`
	Info            deviceInfo      `json:"device"`
	Smartctl        smartctlField   `json:"smartctl"`
	SmartStatus     *smartStatus    `json:"smart_status,omitempty"`
	SmartAttributes smartAttributes `json:"ata_smart_attributes"`
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
	Ids          []int  `json:"{#IDS}"`
}

type smartStatus struct {
	SerialNumber bool `json:"passed"`
}

type smartAttributes struct {
	Table []table `json:"table"`
}

type table struct {
	ID int `json:"id"`
}

var impl Plugin

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	var out []device
	switch key {
	case "smart.disk.discovery":
		basicDev, raidDev, err := getDevices()
		if err != nil {
			return nil, err
		}

		for _, dev := range basicDev {
			deviceJSON, err := executeSmartctl(fmt.Sprintf("-a %s -json", dev.Name))
			if err != nil {
				return nil, fmt.Errorf("Failed to execute smartctl: %s", err.Error())
			}
			// fmt.Println(deviceJSON)
			var dp deviceParser
			if err = json.Unmarshal([]byte(deviceJSON), &dp); err != nil {
				return nil, fmt.Errorf("Failed to unmarshal smartctl response json: %s", err.Error())
			}
			if dp.SmartStatus != nil {
				var ids []int
				for _, table := range dp.SmartAttributes.Table {
					ids = append(ids, table.ID)
				}

				out = append(out, device{dp.Info.Name, dp.Info.DeviceType, dp.ModelName, dp.SerialNumber, ids})
			}
		}

		raidTypes := []string{"areca", "cciss", "megaraid"}
		for _, rDev := range raidDev {
			for _, rtype := range raidTypes {
				raids, err := getRaidDisks(rDev.Name, rtype)
				if err != nil {
					//TODO maybe check error type to be the not found one
					continue
				}

				out = append(out, raids...)
			}
		}
	case "smart.disk.get":
		if len(params) != 1 {
			return nil, errors.New("Invalid first parameter.")
		}
		name := params[0]

		deviceJSON, err := executeSmartctl(fmt.Sprintf("-a %s -json", name))
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

func getRaidDisks(name string, rtype string) ([]device, error) {
	var out []device
	var i int

	//TODO maybe add a timeout ?
	if rtype == "areca" {
		i = 1
	} else {
		i = 0
	}

	for {
		fullName := fmt.Sprintf("%s -d %s,%d", name, rtype, i)
		deviceJSON, err := executeSmartctl(fmt.Sprintf("-a %s -j ", fullName))
		if err != nil {
			return out, fmt.Errorf("Failed to get RAID disk data from smartctl: %s", err.Error())
		}

		var dp deviceParser
		if err = json.Unmarshal([]byte(deviceJSON), &dp); err != nil {
			return out, fmt.Errorf("Failed to unmarshal smartctl RAID response json: %s", err.Error())
		}

		err = checkErr(dp)
		if err != nil {
			return out, fmt.Errorf("Failed to get disk data from smartctl: %s", err.Error())
		}

		var ids []int
		for _, table := range dp.SmartAttributes.Table {
			ids = append(ids, table.ID)
		}

		out = append(out, device{fullName, dp.Info.DeviceType, dp.ModelName, dp.SerialNumber, ids})
		i++
	}
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

func getDevices() (basic []deviceInfo, raid []deviceInfo, err error) {
	raidTmp, err := scanDevices("--scan -d sat -j")
	if err != nil {
		return nil, nil, fmt.Errorf("Failed to scan for sat devices: %s", err)
	}

	basic, err = scanDevices("--scan -j")
	if err != nil {
		return nil, nil, fmt.Errorf("Failed to scan for devices: %s", err)
	}

raid:
	for _, tmp := range raidTmp {
		for _, b := range basic {
			if tmp.Name == b.Name {
				continue raid
			}
		}

		raid = append(raid, tmp)
	}

	return basic, raid, nil
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
