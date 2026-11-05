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

const intrPrefix = "intr "

func (p *Plugin) getCPUIntr(params []string) (any, error) {
	if len(params) != 0 {
		return nil, zbxerr.ErrorTooManyParameters
	}

	line, err := p.findStatLineFunc(intrPrefix)
	if err != nil {
		return nil, err
	}

	total, err := parseIntrTotal(line)
	if err != nil {
		return nil, err
	}

	return total, nil
}

// findProcStatLine returns the first line of /proc/stat that starts with prefix.
func findProcStatLine(prefix string) (string, error) {
	// Read the whole file because the per-interrupt counters can make the intr line very long.
	lines, err := procfs.NewParser().
		SetScanStrategy(procfs.StrategyReadAll).
		SetMaxMatches(1).
		SetPattern(prefix).
		SetMatchMode(procfs.ModePrefix).
		Parse(procStatLocation)
	if err != nil {
		return "", errs.Wrapf(err, "failed to parse %s", procStatLocation)
	}

	if len(lines) == 0 {
		return "", errs.Errorf("cannot find a line with %q in %s", strings.TrimSpace(prefix), procStatLocation)
	}

	return lines[0], nil
}

// parseIntrTotal returns the first counter of the intr line, which is the total number of interrupts since boot.
func parseIntrTotal(line string) (uint64, error) {
	fields := strings.Fields(line)
	if len(fields) < 2 {
		return 0, errs.Errorf("cannot find total interrupt count in line %q", line)
	}

	total, err := strconv.ParseUint(fields[1], 10, 64)
	if err != nil {
		return 0, errs.Wrapf(err, "failed to parse total interrupt count %q", fields[1])
	}

	return total, nil
}
