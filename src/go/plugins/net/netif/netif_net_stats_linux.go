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
	"strconv"
	"strings"

	"golang.zabbix.com/agent2/pkg/procfs"
	"golang.zabbix.com/sdk/errs"
)

const normalAmountOfValuesInNetDev int = 16

var mapNetStatIn = map[string]uint{ //nolint:gochecknoglobals // used as constant check map.
	"bytes":      0,
	"packets":    1,
	"errors":     2,
	"dropped":    3,
	"overruns":   4,
	"frame":      5,
	"compressed": 6,
	"multicast":  7,
}

var mapNetStatOut = map[string]uint{ //nolint:gochecknoglobals // used as constant check map.
	"bytes":      8,
	"packets":    9,
	"errors":     10,
	"dropped":    11,
	"overruns":   12,
	"collisions": 13,
	"carrier":    14,
	"compressed": 15,
}

func (p *Plugin) getNetStats(networkIf, statName string, direction networkDirection) (uint64, error) {
	statNums, err := p.getStatNumbers(statName, direction)
	if err != nil {
		return 0, err
	}

	parser := procfs.NewParser().
		SetScanStrategy(procfs.StrategyOSReadFile).
		SetMatchMode(procfs.ModeContains).
		SetPattern(networkIf).
		SetSplitter(":", 1).
		SetMaxMatches(1)

	data, err := parser.Parse(p.netDevFilepath)
	if err != nil {
		return 0, errs.Wrapf(err, "failed to parse %s", p.netDevFilepath)
	}

	if len(data) != 1 {
		return 0, errs.New(errorCannotFindIf)
	}

	stats := strings.Fields(data[0])

	if len(stats) < normalAmountOfValuesInNetDev {
		return 0, errs.New(errorCannotFindIf)
	}

	return sumStats(stats, statNums)
}

func (*Plugin) getStatNumbers(statName string, direction networkDirection) ([]uint, error) {
	var statNums []uint

	if direction == directionIn || direction == directionTotal {
		statNum, ok := mapNetStatIn[statName]
		if !ok {
			return nil, errs.New(errorInvalidSecondParam)
		}

		statNums = append(statNums, statNum)
	}

	if direction == directionOut || direction == directionTotal {
		statNum, ok := mapNetStatOut[statName]
		if !ok {
			return nil, errs.New(errorInvalidSecondParam)
		}

		statNums = append(statNums, statNum)
	}

	return statNums, nil
}

func sumStats(stats []string, statNums []uint) (uint64, error) {
	var total uint64

	for _, statNum := range statNums {
		res, err := strconv.ParseUint(stats[statNum], 10, 64)
		if err != nil {
			return 0, errs.New(errorCannotFindIf)
		}

		total += res
	}

	return total, nil
}
