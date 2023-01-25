/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

package sw

import (
	"git.zabbix.com/ap/plugin-support/plugin"
	"git.zabbix.com/ap/plugin-support/zbxerr"
)

// Plugin -
type Plugin struct {
	plugin.Base
	options Options
}

// Options -
type Options struct {
	plugin.SystemOptions `conf:"optional,name=System"`
	Timeout              int
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
	const (
		maxSwPackagesParams = 3
		maxSwOSParams       = 1
		maxSwOSGetParams    = 0
	)

	switch key {
	case "system.sw.packages":
		result, err = p.systemSwPackages(params)

	case "system.sw.packages.get":
		result, err = p.systemSwPackagesGet(params)

	case "system.sw.os":
		if len(params) > maxSwOSParams {
			return nil, zbxerr.ErrorTooManyParameters
		}

		result, err = p.getOSVersion(params)

	case "system.sw.os.get":
		if len(params) > maxSwOSGetParams {
			return nil, zbxerr.ErrorTooManyParameters
		}
		result, err = p.getOSVersionJSON()

	default:
		return nil, plugin.UnsupportedMetricError
	}

	return
}

func init() {
	plugin.RegisterMetrics(&impl, "Sw",
		"system.sw.packages", "Lists installed packages whose name matches the given package regular expression.",
		"system.sw.packages.get", "Lists matching installed packages with details in JSON format.",
		"system.sw.os", "Operating system information.",
		"system.sw.os.get", "Operating system information in JSON format.",
	)
}
