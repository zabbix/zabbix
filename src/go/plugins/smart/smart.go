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
	"errors"
	"fmt"
	"strconv"
	"strings"

	"zabbix.com/pkg/plugin"
)

const supportedSmartctl = 7.1

// Devices -
type Devices struct {
	Devices []deviceInfo `json:"devices"`
}

//Device -
type Device struct {
	Name         string `json:"{#NAME}"`
	DeviceType   string `json:"{#DISKTYPE}"`
	Model        string `json:"{#MODEL}"`
	SerialNumber string `json:"{#SN}"`
}

// Attribute -
type Attribute struct {
	Name       string `json:"{#NAME}"`
	DeviceType string `json:"{#DISKTYPE}"`
	ID         int    `json:"{#ID}"`
	Attrname   string `json:"{#ATTRNAME}"`
	Thresh     int    `json:"{#THRESH}"`
}

//DeviceParser -
type DeviceParser struct {
	ModelName       string          `json:"model_name"`
	SerialNumber    string          `json:"serial_number"`
	RotationRate    int             `json:"rotation_rate"`
	Info            deviceInfo      `json:"device"`
	Smartctl        smartctlField   `json:"smartctl"`
	SmartStatus     *smartStatus    `json:"smart_status,omitempty"`
	SmartAttributes smartAttributes `json:"ata_smart_attributes"`
}

type deviceInfo struct {
	Name    string `json:"name"`
	DevType string `json:"type"`
}

type smartctl struct {
	Smartctl smartctlField `json:"smartctl"`
}

type smartctlField struct {
	Messages   []message `json:"messages"`
	ExitStatus int       `json:"exit_status"`
	Version    []int     `json:"version"`
}

type message struct {
	Str      string `json:"string"`
	Severity string `json:"severity"`
}

type smartStatus struct {
	SerialNumber bool `json:"passed"`
}

type smartAttributes struct {
	Table []table `json:"table"`
}

type table struct {
	Attrname string `json:"name"`
	ID       int    `json:"id"`
	Thresh   int    `json:"thresh"`
}

//GetParsedDevices -
func GetParsedDevices(p *plugin.Base, timeout int) ([]DeviceParser, error) {
	var out []DeviceParser
	found := make(map[string]bool)
	basicDev, raidDev, err := getDevices(timeout)
	if err != nil {
		return nil, err
	}

	for _, dev := range basicDev {
		deviceJSON, err := executeSmartctl(fmt.Sprintf("-a %s -json", dev.Name), timeout)
		if err != nil {
			return nil, fmt.Errorf("Failed to execute smartctl: %s", err.Error())
		}

		var dp DeviceParser
		if err = json.Unmarshal([]byte(deviceJSON), &dp); err != nil {
			return nil, fmt.Errorf("Failed to unmarshal smartctl response json: %s", err.Error())
		}

		if err = dp.checkErr(); err != nil {
			return nil, fmt.Errorf("Smartctl failed to get device data: %s", err.Error())
		}

		if dp.SmartStatus != nil {
			if !found[dp.SerialNumber] {
				found[dp.SerialNumber] = true
				out = append(out, dp)
			}
		}
	}

	raidTypes := []string{"3ware", "areca", "cciss", "megaraid"}
	for _, rDev := range raidDev {
		for _, rtype := range raidTypes {
			raids, err := getRaidDisks(rDev.Name, rtype, timeout)
			if err != nil {
				p.Tracef("stopped looking for RAID devices of %s type, found %d, err:", rtype, len(raids), err.Error())
			}
			out = append(out, raids...)
		}
	}

	return out, err
}

//GetDeviceJsons -
func GetDeviceJsons(p *plugin.Base, timeout int) (map[string]string, error) {
	out := make(map[string]string)
	basicDev, raidDev, err := getDevices(timeout)
	if err != nil {
		return nil, err
	}

	for _, dev := range basicDev {
		deviceJSON, err := executeSmartctl(fmt.Sprintf("-a %s -json", dev.Name), timeout)
		if err != nil {
			return nil, fmt.Errorf("Failed to execute smartctl: %s", err.Error())
		}
		out[dev.Name] = deviceJSON
	}

	raidTypes := []string{"3ware", "areca", "cciss", "megaraid"}
	for _, rDev := range raidDev {
		for _, rtype := range raidTypes {
			raids, err := getRaidJsons(rDev.Name, rtype, timeout)
			if err != nil {
				p.Tracef("Stopped looking for RAID devices of %s type, found %d, err:", rtype, len(raids), err.Error())
			}
			for k, v := range raids {
				out[k] = v
			}
		}
	}

	return out, nil
}

// CheckVerson -
func CheckVerson(p *plugin.Base, timeout int) error {
	var smartctl smartctl
	devicesJSON, err := executeSmartctl("--scan -j", timeout)
	if err != nil {
		p.Errf("failed to execute smartctl: %s", err.Error())
		return fmt.Errorf("Could not execute smartctl")
	}

	if err = json.Unmarshal([]byte(devicesJSON), &smartctl); err != nil {
		return fmt.Errorf("Fail to unmarshal smartctl response: %s", err)
	}

	if len(smartctl.Smartctl.Version) < 1 {
		return fmt.Errorf("Invalid smartctl version")
	}
	var version string
	if len(smartctl.Smartctl.Version) >= 2 {
		version = fmt.Sprintf("%d.%d", smartctl.Smartctl.Version[0], smartctl.Smartctl.Version[1])
	} else {
		version = fmt.Sprintf("%d", smartctl.Smartctl.Version[0])
	}

	v, err := strconv.ParseFloat(version, 64)
	if err != nil {
		return fmt.Errorf("Failed to parse smartctl version")
	}

	if v < supportedSmartctl {
		return fmt.Errorf("Incorrect smartctl version, must be %v or higher", supportedSmartctl)
	}

	return nil
}

// CutPrefix -
func CutPrefix(in string) string {
	return strings.TrimPrefix(in, "/dev/")
}

func getRaidJsons(name string, rtype string, timeout int) (map[string]string, error) {
	out := make(map[string]string)
	var i int
	if rtype == "areca" {
		i = 1
	} else {
		i = 0
	}

	for {
		fullName := fmt.Sprintf("%s -d %s,%d", name, rtype, i)
		deviceJSON, err := executeSmartctl(fmt.Sprintf("-a %s -j ", fullName), timeout)
		if err != nil {
			return out, fmt.Errorf("failed to get RAID disk data from smartctl: %s", err.Error())
		}

		var dp DeviceParser
		if err = json.Unmarshal([]byte(deviceJSON), &dp); err != nil {
			return out, fmt.Errorf("failed to unmarshal smartctl RAID response json: %s", err.Error())
		}

		err = dp.checkErr()
		if err != nil {
			return out, fmt.Errorf("failed to get disk data from smartctl: %s", err.Error())
		}

		out[fullName] = deviceJSON
		i++
	}
}

func getRaidDisks(name string, rtype string, timeout int) ([]DeviceParser, error) {
	var out []DeviceParser
	var i int
	if rtype == "areca" {
		i = 1
	} else {
		i = 0
	}

	for {
		fullName := fmt.Sprintf("%s -d %s,%d", name, rtype, i)
		deviceJSON, err := executeSmartctl(fmt.Sprintf("-a %s -j ", fullName), timeout)
		if err != nil {
			return out, fmt.Errorf("failed to get RAID disk data from smartctl: %s", err.Error())
		}

		var dp DeviceParser
		if err = json.Unmarshal([]byte(deviceJSON), &dp); err != nil {
			return out, fmt.Errorf("failed to unmarshal smartctl RAID response json: %s", err.Error())
		}

		err = dp.checkErr()
		if err != nil {
			return out, fmt.Errorf("failed to get disk data from smartctl: %s", err.Error())
		}

		dp.Info.Name = fullName
		out = append(out, dp)
		i++
	}
}

func (dp DeviceParser) checkErr() (err error) {
	if dp.Smartctl.ExitStatus != 2 {
		return
	}

	for _, m := range dp.Smartctl.Messages {
		if err == nil {
			err = errors.New(m.Str)
			continue
		}

		err = fmt.Errorf("%s, %s", err.Error(), m.Str)
	}

	if err == nil {
		err = fmt.Errorf("unknown error from smartctl")
	}

	return
}

func getDevices(timeout int) (basic []deviceInfo, raid []deviceInfo, err error) {
	basic, err = scanDevices("--scan -j", timeout)
	if err != nil {
		return nil, nil, fmt.Errorf("Failed to scan for devices: %s", err)
	}

	raidTmp, err := scanDevices("--scan -d sat -j", timeout)
	if err != nil {
		return nil, nil, fmt.Errorf("Failed to scan for sat devices: %s", err)
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

func scanDevices(args string, timeout int) ([]deviceInfo, error) {
	var devices Devices
	devicesJSON, err := executeSmartctl(args, timeout)
	if err != nil {
		return nil, err
	}

	if err = json.Unmarshal([]byte(devicesJSON), &devices); err != nil {
		return nil, err
	}

	return devices.Devices, nil
}
