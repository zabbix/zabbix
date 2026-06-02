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

package netif

import (
	"golang.zabbix.com/agent2/pkg/procfs"
	"golang.zabbix.com/sdk/errs"
)

func (p *Plugin) getDevDiscovery() ([]msgIfDiscovery, error) {
	parser := procfs.NewParser().
		SetScanStrategy(procfs.StrategyOSReadFile).
		SetMatchMode(procfs.ModeContains).
		SetPattern(":").
		SetSplitter(":", 0)

	data, err := parser.Parse(p.netDevFilepath)
	if err != nil {
		return nil, errs.Wrapf(err, "failed to parse %s file", p.netDevFilepath)
	}

	result := make([]msgIfDiscovery, 0, len(data))
	for _, line := range data {
		result = append(result, msgIfDiscovery{line, nil})
	}

	return result, nil
}
