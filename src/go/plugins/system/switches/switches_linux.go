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

package switches

import (
	"strconv"
	"strings"

	"golang.zabbix.com/agent2/pkg/procfs"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

const pluginName = "CpuSwitches"

const (
	procStatFilepath = "/proc/stat"
	pattern          = "ctxt"
)

var (
	errCtxtNotFound     = errs.New("ctxt line not found")
	errParamsNotAllowed = errs.New("item does not allow parameters")
)

// Plugin is a base struct for a plugin.
type Plugin struct {
	plugin.Base
}

//nolint:gochecknoinits
func init() {
	impl := &Plugin{}

	err := plugin.RegisterMetrics(
		impl, pluginName,
		"system.cpu.switches", "Count of context switches.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

// Export implements plugin.Exporter interface.
func (*Plugin) Export(_ string, params []string, _ plugin.ContextProvider) (any, error) {
	if len(params) != 0 {
		return nil, errParamsNotAllowed
	}

	parser := procfs.NewParser().
		SetScanStrategy(procfs.StrategyOSReadFile).
		SetPattern(pattern).
		SetMatchMode(procfs.ModePrefix)

	lines, err := parser.Parse(procStatFilepath)
	if err != nil {
		return nil, errs.Wrapf(err, "failed to parse %q", procStatFilepath)
	}

	value, err := parseSwitches(lines)
	if err != nil {
		return nil, errs.Wrapf(err, "failed to get context switches from %q", procStatFilepath)
	}

	return value, nil
}

// parseSwitches returns the value of the first parsable "ctxt" line.
func parseSwitches(lines []string) (uint64, error) {
	for _, line := range lines {
		value, ok := parseValue(line)
		if ok {
			return value, nil
		}
	}

	return 0, errCtxtNotFound
}

// parseValue mirrors C sscanf(line, "%*s %llu"): leading digits of the second field.
func parseValue(line string) (uint64, bool) {
	fields := strings.Fields(line)
	if len(fields) < 2 {
		return 0, false
	}

	digits := fields[1]

	end := strings.IndexFunc(digits, func(r rune) bool { return r < '0' || r > '9' })
	if end >= 0 {
		digits = digits[:end]
	}

	value, err := strconv.ParseUint(digits, 10, 64)
	if err != nil {
		return 0, false
	}

	return value, true
}
