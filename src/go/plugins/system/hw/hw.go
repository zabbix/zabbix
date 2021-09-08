// +build !windows

/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

package hw

import (
	"time"

	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/zbxcmd"
	"zabbix.com/pkg/zbxerr"
)

const (
	pciCMD = "lspci"
	usbCMD = "lsusb"
)

// Plugin -
type Plugin struct {
	plugin.Base
	options Options
}

// Options -
type Options struct {
	Timeout int
}

type manager struct {
	name    string
	testCmd string
	cmd     string
	parser  func(in []string, regex string) ([]string, error)
}

var impl Plugin

// Configure -
func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	p.options.Timeout = global.Timeout
}

// Validate -
func (p *Plugin) Validate(options interface{}) error { return nil }

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "system.hw.chassis":
		return p.exportChassis(params)
	case "system.hw.devices":
		return p.exportDevices(params)
	default:
		return nil, plugin.UnsupportedMetricError
	}
}

func (p *Plugin) exportChassis(params []string) (result interface{}, err error) {
	if len(params) > 1 {
		return nil, zbxerr.ErrorTooManyParameters
	}

	return
}

func (p *Plugin) exportDevices(params []string) (result interface{}, err error) {
	cmd, err := getDeviceCmd(params)
	if err != nil {
		return
	}

	return zbxcmd.ExecuteStrict(cmd, time.Second*time.Duration(p.options.Timeout))
}

func getDeviceCmd(params []string) (string, error) {
	switch len(params) {
	case 1:
		switch params[0] {
		case "pci", "":
			return pciCMD, nil
		case "usb":
			return usbCMD, nil
		default:
			return "", zbxerr.New("invalid first parameter")
		}
	case 0:
		return pciCMD, nil
	default:
		return "", zbxerr.ErrorTooManyParameters
	}
}

func init() {
	plugin.RegisterMetrics(&impl, "Hw",
		"system.hw.chassis", "Chassis information.",
		"system.hw.devices", "Listing of PCI or USB devices.",
	)
}
