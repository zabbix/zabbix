/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

package sw

import (
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

var impl Plugin

// Plugin -
type Plugin struct {
	plugin.Base
}

func init() {
	err := plugin.RegisterMetrics(
		&impl, "Sw",
		"system.sw.packages", "Lists installed packages whose name matches the given package regular expression.",
		"system.sw.packages.get", "Lists matching installed packages with details in JSON format.",
		"system.sw.os", "Operating system information.",
		"system.sw.os.get", "Operating system information in JSON format.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

// Configure -
func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
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
		result, err = p.systemSwPackages(params, ctx.Timeout())

	case "system.sw.packages.get":
		result, err = p.systemSwPackagesGet(params, ctx.Timeout())

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
