//go:build windows
// +build windows

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

package swap

import (
	"golang.zabbix.com/agent2/pkg/win32"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

func init() {
	err := plugin.RegisterMetrics(&impl, "Swap",
		"system.swap.size", "Returns Swap space size in bytes or in percentage from total.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

func getSwapSize() (uint64, uint64, error) {
	m, err := win32.GlobalMemoryStatusEx()
	if err != nil {
		return 0, 0, nil
	}

	var total, avail uint64
	if m.TotalPageFile > m.TotalPhys {
		total = m.TotalPageFile - m.TotalPhys
	}
	if m.AvailPageFile > m.AvailPhys {
		avail = m.AvailPageFile - m.AvailPhys
	}

	return total, avail, nil
}

func getSwapStatsIn(string) (uint64, uint64, uint64, error) {
	return 0, 0, 0, plugin.UnsupportedMetricError
}

func getSwapStatsOut(string) (uint64, uint64, uint64, error) {
	return 0, 0, 0, plugin.UnsupportedMetricError
}
