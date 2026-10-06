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
	"errors"
	"strconv"
	"strings"
	"syscall"

	"golang.zabbix.com/agent2/pkg/procfs"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

const pluginName = "CpuSwitches"

const (
	procStatFilepath = "/proc/stat"
	pattern          = "ctxt"
)

// Error texts match the C agent's system_cpu_switches(), which always names /proc/stat.
var errCtxtNotFound = errs.New(`cannot find a line with "ctxt" in /proc/stat`)

// Plugin is a base struct for a plugin.
type Plugin struct {
	plugin.Base

	procStatFilepath string
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

	impl.procStatFilepath = procStatFilepath
}

// Export implements plugin.Exporter interface. Parameters are ignored, as in the C agent.
func (p *Plugin) Export(_ string, _ []string, _ plugin.ContextProvider) (any, error) {
	parser := procfs.NewParser().
		SetScanStrategy(procfs.StrategyOSReadFile).
		SetPattern(pattern).
		SetMatchMode(procfs.ModePrefix)

	lines, err := parser.Parse(p.procStatFilepath)
	if err != nil {
		return nil, openError(err)
	}

	for _, line := range lines {
		value, ok := parseValue(line)
		if ok {
			return value, nil
		}
	}

	return nil, errCtxtNotFound
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

// openError formats a read failure like the C agent: "Cannot open /proc/stat: [errno] strerror".
func openError(err error) error {
	var errno syscall.Errno

	if !errors.As(err, &errno) {
		return errs.Wrap(err, "cannot open /proc/stat")
	}

	// Go's errno texts are glibc's strerror() with a lowercase first letter.
	text := errno.Error()

	return errs.Errorf("cannot open /proc/stat: [%d] %s", int(errno), strings.ToUpper(text[:1])+text[1:])
}
