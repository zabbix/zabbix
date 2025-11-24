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

package kernel

import (
	"strconv"

	"golang.zabbix.com/agent2/pkg/procfs"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

// getFirstNum  - get first number from file
func getFirstNum(key string) (uint64, error) {
	fileName := "/proc"

	switch key {
	case "kernel.maxproc":
		fileName += "/sys/kernel/pid_max"
	case "kernel.maxfiles":
		fileName += "/sys/fs/file-max"
	case "kernel.openfiles":
		fileName += "/sys/fs/file-nr"
	default:
		return 0, plugin.UnsupportedMetricError
	}

	parser := procfs.NewParser().
		SetScanStrategy(procfs.StrategyReadAll).
		SetMaxMatches(1)

	if key == "kernel.openfiles" {
		parser.SetSplitter("\t", 0) // there are several values in the file
	}

	data, err := parser.Parse(fileName)
	if err != nil {
		return 0, errs.Wrapf(err, "failed to read %s", fileName)
	}

	if len(data) == 0 {
		return 0, errs.Errorf("failed to parse %s", fileName)
	}

	maximum, err := strconv.ParseUint(data[0], 10, 64)
	if err != nil {
		return 0, errs.Wrapf(err, "Cannot obtain data from %s.", fileName)
	}

	return maximum, nil
}
