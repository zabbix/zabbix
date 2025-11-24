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

package boottime

import (
	"golang.zabbix.com/agent2/pkg/procfs"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

const pluginName = "Boottime"

var impl Plugin //nolint:gochecknoglobals

// Plugin is a base struct for a plugin.
type Plugin struct {
	plugin.Base
}

//nolint:gochecknoinits
func init() {
	err := plugin.RegisterMetrics(
		&impl, pluginName,
		"system.boottime", "Returns system boot time in unixtime.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

// Export implements Exporter interface.
func (*Plugin) Export(_ string, params []string, _ plugin.ContextProvider) (any, error) {
	if len(params) != 0 {
		return nil, zbxerr.ErrorTooManyParameters
	}

	parser := procfs.NewParser().
		SetPattern("btime").
		SetMaxMatches(1).
		SetMatchMode(procfs.ModePrefix).
		SetSplitter("btime", 1)

	result, err := parser.Parse("/proc/stat")
	if err != nil {
		return nil, errs.Wrap(err, "failed to parse /proc/stat")
	}

	if len(result) != 1 {
		return nil, errs.New("unexpected result")
	}

	return result[0], nil
}
