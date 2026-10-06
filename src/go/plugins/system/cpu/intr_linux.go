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

package cpu

import (
	"strconv"
	"strings"

	"golang.zabbix.com/agent2/pkg/procfs"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

const intrPattern = "intr"

func (p *Plugin) getCPUIntr(params []string) (any, error) {
	if len(params) != 0 {
		return nil, zbxerr.ErrorTooManyParameters
	}

	// Read the whole file because the per-interrupt counters can make the intr line very long.
	parser := procfs.NewParser().
		SetScanStrategy(procfs.StrategyReadAll).
		SetMaxMatches(1).
		SetPattern(intrPattern).
		SetMatchMode(procfs.ModePrefix)

	data, err := parser.Parse(p.procStatFilepath)
	if err != nil {
		return nil, errs.Wrapf(err, "failed to parse %s", p.procStatFilepath)
	}

	var (
		result uint64
		found  bool
	)

	for _, line := range data {
		fields := strings.Fields(line)
		if len(fields) < 2 || fields[0] != intrPattern {
			continue
		}

		// The first counter is the total number of interrupts since boot.
		result, err = strconv.ParseUint(fields[1], 10, 64)
		if err == nil {
			found = true

			break
		}
	}

	if !found {
		return nil, errs.Errorf("Cannot find a line with %q in %s.", intrPattern, p.procStatFilepath)
	}

	return result, nil
}
