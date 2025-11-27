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

package netif

import (
	"encoding/json"
	"strconv"
	"strings"

	"golang.zabbix.com/agent2/pkg/procfs"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

const (
	errorCannotFindIf = "Cannot find information for this network interface in /proc/net/dev."
	netDevFilepath    = "/proc/net/dev"
)

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

func init() { //nolint:gochecknoinits // legacy implementation
	impl := &Plugin{}

	err := plugin.RegisterMetrics(
		impl, "NetIf",
		"net.if.collisions", "Returns number of out-of-window collisions.",
		"net.if.in", "Returns incoming traffic statistics on network interface.",
		"net.if.out", "Returns outgoing traffic statistics on network interface.",
		"net.if.total", "Returns sum of incoming and outgoing traffic statistics on network interface.",
		"net.if.discovery", "Returns list of network interfaces. Used for low-level discovery.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}

	impl.netDevFilepath = netDevFilepath
}

//nolint:gocyclo,cyclop // file parsing takes a lot of ifs in this case.
func (p *Plugin) getNetStats(networkIf, statName string, direction networkDirection) (uint64, error) {
	var statNums []uint

	if direction == directionIn || direction == directionTotal {
		statNum, ok := mapNetStatIn[statName]
		if !ok {
			return 0, errs.New(errorInvalidSecondParam)
		}

		statNums = append(statNums, statNum)
	}

	if direction == directionOut || direction == directionTotal {
		statNum, ok := mapNetStatOut[statName]
		if !ok {
			return 0, errs.New(errorInvalidSecondParam)
		}

		statNums = append(statNums, statNum)
	}

	parser := procfs.NewParser().
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

	var total uint64

	if len(stats) < 16 {
		return 0, errs.New(errorCannotFindIf)
	}

	for _, statNum := range statNums {
		res, err := strconv.ParseUint(stats[statNum], 10, 64)
		if err != nil {
			return 0, errs.New(errorCannotFindIf)
		}

		total += res
	}

	return total, nil
}

func (p *Plugin) getDevDiscovery() ([]msgIfDiscovery, error) {
	parser := procfs.NewParser().
		SetMatchMode(procfs.ModeContains).
		SetPattern(":").
		SetSplitter(":", 0)

	data, err := parser.Parse(p.netDevFilepath)
	if err != nil {
		return nil, errs.Wrap(err, "failed to parse /proc/net/dev file")
	}

	result := make([]msgIfDiscovery, 0, len(data))
	for _, line := range data {
		result = append(result, msgIfDiscovery{line, nil})
	}

	return result, nil
}

// Export implements plugin.Exporter interface.
//
//nolint:gocyclo,cyclop // export function delegates its requests, so high cyclo is expected.
func (p *Plugin) Export(key string, params []string, _ plugin.ContextProvider) (any, error) {
	var direction networkDirection

	switch key {
	case "net.if.discovery":
		if len(params) > 0 {
			return nil, errs.New(errorParametersNotAllowed)
		}

		devices, err := p.getDevDiscovery()
		if err != nil {
			return nil, err
		}

		b, err := json.Marshal(devices)
		if err != nil {
			return nil, errs.Wrap(err, "failed to marshal devices")
		}

		return string(b), nil
	case "net.if.collisions":
		if len(params) > 1 {
			return nil, errs.New(errorTooManyParams)
		}

		if len(params) < 1 || params[0] == "" {
			return nil, errs.New(errorEmptyIfName)
		}

		return p.getNetStats(params[0], "collisions", directionOut)
	case "net.if.in":
		direction = directionIn
	case "net.if.out":
		direction = directionOut
	case "net.if.total":
		direction = directionTotal
	default:
		/* SHOULD_NEVER_HAPPEN */
		return nil, errs.New(errorUnsupportedMetric)
	}

	if len(params) < 1 || params[0] == "" {
		return nil, errs.New(errorEmptyIfName)
	}

	if len(params) > 2 {
		return nil, errs.New(errorTooManyParams)
	}

	var mode string
	if len(params) == 2 && params[1] != "" {
		mode = params[1]
	} else {
		mode = "bytes"
	}

	return p.getNetStats(params[0], mode, direction)
}
