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

	"zabbix.com/pkg/zbxerr"
)

const supportedSmartctl = 7.1

type devices struct {
	Info []deviceInfo `json:"devices"`
}

type device struct {
	Name         string `json:"{#NAME}"`
	DeviceType   string `json:"{#DISKTYPE}"`
	Model        string `json:"{#MODEL}"`
	SerialNumber string `json:"{#SN}"`
}

type attribute struct {
	Name       string `json:"{#NAME}"`
	DeviceType string `json:"{#DISKTYPE}"`
	ID         int    `json:"{#ID}"`
	Attrname   string `json:"{#ATTRNAME}"`
	Thresh     int    `json:"{#THRESH}"`
}

type deviceParser struct {
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
	Str string `json:"string"`
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

// getParsedDevices returns a parsed slice of all devices returned by smartctl.
// Currently looks for 4 raid types "3ware", "areca", "cciss", "megaraid".
// It returns an error if there is an issue with getting or parsing results from smartctl.
func (p *Plugin) getParsedDevices() ([]deviceParser, error) {
	var out []deviceParser

	found := make(map[string]bool)

	basicDev, raidDev, err := p.getDevices()
	if err != nil {
		return nil, err
	}

	for _, dev := range basicDev {
		devices, err := p.executeSmartctl(fmt.Sprintf("-a %s -j", dev.Name), false)
		if err != nil {
			return nil, fmt.Errorf("Failed to execute smartctl: %s.", err.Error())
		}

		var dp deviceParser
		if err = json.Unmarshal(devices, &dp); err != nil {
			return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
		}

		if err = dp.checkErr(); err != nil {
			return nil, fmt.Errorf("Smartctl failed to get device data: %s.", err.Error())
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
			raids, err := p.getRaidDisks(rDev.Name, rtype)
			if err != nil {
				p.Tracef("stopped looking for RAID devices of %s type, found %d, err:", rtype, len(raids), err.Error())
			}

			out = append(out, raids...)
		}
	}

	return out, err
}

// getDeviceJsons returns a map of all devices returned by smartctl.
// The return map key contains the device name and value is the device data.
// Currently looks for 4 raid types "3ware", "areca", "cciss", "megaraid".
// It returns an error if there is an issue with getting or parsing results from smartctl.
func (p *Plugin) getDeviceJsons() (map[string]string, error) {
	out := make(map[string]string)

	basicDev, raidDev, err := p.getDevices()
	if err != nil {
		return nil, err
	}

	for _, dev := range basicDev {
		devices, err := p.executeSmartctl(fmt.Sprintf("-a %s -j", dev.Name), false)
		if err != nil {
			return nil, fmt.Errorf("Failed to execute smartctl: %s.", err.Error())
		}

		var dp deviceParser
		if err = json.Unmarshal(devices, &dp); err != nil {
			return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
		}

		if err = dp.checkErr(); err != nil {
			return nil, fmt.Errorf("Smartctl failed to get device data: %s.", err.Error())
		}

		if dp.SmartStatus != nil {
			out[dev.Name] = string(devices)
		}
	}

	raidTypes := []string{"3ware", "areca", "cciss", "megaraid"}

	for _, rDev := range raidDev {
		for _, rtype := range raidTypes {
			raids, err := p.getRaidJsons(rDev.Name, rtype)
			if err != nil {
				p.Tracef("stopped looking for RAID devices of %s type, found %d, err:", rtype, len(raids), err.Error())
			}

			for k, v := range raids {
				out[k] = v
			}
		}
	}

	return out, nil
}

// checkVersion checks the version of smartctl.
// Currently supported versions are 7.1 and above.
// It returns an error if there is an issue with getting or parsing results from smartctl.
func (p *Plugin) checkVersion() error {
	var smartctl smartctl

	info, err := p.executeSmartctl("-j -V", true)
	if err != nil {
		return fmt.Errorf("Failed to execute smartctl: %s.", err.Error())
	}

	if err = json.Unmarshal(info, &smartctl); err != nil {
		return zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
	}

	return evaluateVersion(smartctl.Smartctl.Version)
}

func evaluateVersion(versionDigits []int) error {
	if len(versionDigits) < 1 {
		return fmt.Errorf("Invalid smartctl version")
	}

	var version string
	if len(versionDigits) >= 2 {
		version = fmt.Sprintf("%d.%d", versionDigits[0], versionDigits[1])
	} else {
		version = fmt.Sprintf("%d", versionDigits[0])
	}

	v, err := strconv.ParseFloat(version, 64)
	if err != nil {
		return zbxerr.ErrorCannotParseResult.Wrap(err)
	}

	if v < supportedSmartctl {
		return fmt.Errorf("Incorrect smartctl version, must be %v or higher", supportedSmartctl)
	}

	return nil
}

func cutPrefix(in string) string {
	return strings.TrimPrefix(in, "/dev/")
}

// getRaidJsons returns a map of raid devices returned by smartctl.
// Executes smartctl to look for specific raid devices by name and type.
// The return map key contains the device name and value is the device data.
// It returns an error if there is an issue with getting or parsing results from smartctl.
func (p *Plugin) getRaidJsons(name, rtype string) (map[string]string, error) {
	out := make(map[string]string)

	var i int

	if rtype == "areca" {
		i = 1
	} else {
		i = 0
	}

	for {
		device, err := p.executeSmartctl(fmt.Sprintf("-a %s -j ", fmt.Sprintf("%s -d %s,%d", name, rtype, i)), false)
		if err != nil {
			return out, fmt.Errorf("failed to get RAID disk data from smartctl: %s", err.Error())
		}

		var dp deviceParser
		if err = json.Unmarshal(device, &dp); err != nil {
			return out, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
		}

		err = dp.checkErr()
		if err != nil {
			return out, fmt.Errorf("failed to get disk data from smartctl: %s", err.Error())
		}

		out[fmt.Sprintf("%s %s,%d", name, rtype, i)] = string(device)
		i++
	}
}

// getRaidDisks returns a parsed slice of all devices returned by smartctl.
// Executes smartctl to look for specific raid devices by name and type.
// It returns an error if there is an issue with getting or parsing results from smartctl.
func (p *Plugin) getRaidDisks(name, rtype string) ([]deviceParser, error) {
	var out []deviceParser

	var i int

	if rtype == "areca" {
		i = 1
	} else {
		i = 0
	}

	for {
		device, err := p.executeSmartctl(fmt.Sprintf("-a %s -j ", fmt.Sprintf("%s -d %s,%d", name, rtype, i)), false)
		if err != nil {
			return out, fmt.Errorf("failed to get RAID disk data from smartctl: %s", err.Error())
		}

		var dp deviceParser
		if err = json.Unmarshal(device, &dp); err != nil {
			return out, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
		}

		err = dp.checkErr()
		if err != nil {
			return out, fmt.Errorf("failed to get disk data from smartctl: %s", err.Error())
		}

		dp.Info.Name = fmt.Sprintf("%s %s,%d", name, rtype, i)
		out = append(out, dp)
		i++
	}
}

func (dp *deviceParser) checkErr() (err error) {
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
		err = errors.New("unknown error from smartctl")
	}

	return
}

// getDevices returns a parsed slices of all devices returned by smartctl scan.
// Returns a separate slice for both normal and raid devices.
// It returns an error if there is an issue with getting or parsing results from smartctl.
func (p *Plugin) getDevices() (basic, raid []deviceInfo, err error) {
	basic, err = p.scanDevices("--scan -j")
	if err != nil {
		return nil, nil, fmt.Errorf("Failed to scan for devices: %s.", err)
	}

	raidTmp, err := p.scanDevices("--scan -d sat -j")
	if err != nil {
		return nil, nil, fmt.Errorf("Failed to scan for sat devices: %s.", err)
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

// scanDevices executes smartctl.
// It parses the smartctl data into a slice with deviceInfo.
// It returns an error if there is an issue with getting or parsing results from smartctl.
func (p *Plugin) scanDevices(args string) ([]deviceInfo, error) {
	var d devices

	devices, err := p.executeSmartctl(args, false)
	if err != nil {
		return nil, err
	}

	if err = json.Unmarshal(devices, &d); err != nil {
		return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
	}

	return d.Info, nil
}
