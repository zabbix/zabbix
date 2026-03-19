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

package boottime

import (
	"fmt"
	"strconv"
	"strings"

	"golang.zabbix.com/agent2/pkg/procfs"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

const pluginName = "Boottime"

const (
	procStatFilepath = "/proc/stat"
	pattern          = "btime"
)

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
		"system.boottime", "Returns system boot time in unixtime.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}

	impl.procStatFilepath = procStatFilepath
}

// Export implements plugin.Exporter interface.
func (p *Plugin) Export(_ string, params []string, _ plugin.ContextProvider) (any, error) {
	if len(params) != 0 {
		return nil, zbxerr.ErrorTooManyParameters
	}

	parser := procfs.NewParser().
		SetScanStrategy(procfs.StrategyOSReadFile).
		SetPattern(pattern).
		SetMatchMode(procfs.ModePrefix).
		SetSplitter(pattern, 1)

	data, err := parser.Parse(p.procStatFilepath)
	if err != nil {
		return nil, errs.Wrapf(err, "failed to parse %s", p.procStatFilepath)
	}

	var (
		result uint64
		found  bool
	)

	for _, d := range data {
		result, err = strconv.ParseUint(strings.TrimSpace(d), 10, 64)
		if err == nil {
			found = true

			break
		}
	}

	if !found {
		return nil, errs.New(fmt.Sprintf("Cannot find a line with %q in %s.", pattern, p.procStatFilepath))
	}

	return result, nil
}
