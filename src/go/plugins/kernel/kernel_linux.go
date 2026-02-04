/*
** Copyright (C) 2001-2026 Zabbix SIA
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

package kernel

import (
	"strconv"

	"golang.zabbix.com/agent2/pkg/procfs"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

// gatherData  - get first number from file.
func (p *Plugin) gatherData(key string) (uint64, error) {
	var fileName string

	switch key {
	case "kernel.maxproc":
		fileName = p.pidMaxPath
	case "kernel.maxfiles":
		fileName = p.fileMaxPath
	case "kernel.openfiles":
		fileName = p.fileNrPath
	default:
		return 0, plugin.UnsupportedMetricError
	}

	parser := procfs.NewParser().
		SetScanStrategy(procfs.StrategyReadAll).
		SetMaxMatches(1)

	if key == "kernel.openfiles" {
		parser.SetSplitter("\t", 0) // there are several values in the line.
	}

	data, err := parser.Parse(fileName)
	if err != nil {
		return 0, errs.Wrapf(err, "failed to read %s", fileName)
	}

	if len(data) == 0 {
		return 0, errs.Errorf("failed to parse %s", fileName)
	}

	result, err := strconv.ParseUint(data[0], 10, 64)
	if err != nil {
		return 0, errs.Wrapf(err, "data obtained from %s was invalid", fileName)
	}

	return result, nil
}
