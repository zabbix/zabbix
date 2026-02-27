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

package kernel

import (
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	pidMaxPath  = "/proc/sys/kernel/pid_max"
	fileMaxPath = "/proc/sys/fs/file-max"
	fileNrPath  = "/proc/sys/fs/file-nr"
)

// Plugin -
type Plugin struct {
	plugin.Base

	pidMaxPath  string
	fileMaxPath string
	fileNrPath  string
}

func init() {
	impl := &Plugin{}

	err := plugin.RegisterMetrics(
		impl, "Kernel",
		"kernel.maxproc", "Returns maximum number of processes supported by OS.",
		"kernel.maxfiles", "Returns maximum number of opened files supported by OS.",
		"kernel.openfiles", "Returns number of currently open file descriptors.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}

	impl.pidMaxPath = pidMaxPath
	impl.fileMaxPath = fileMaxPath
	impl.fileNrPath = fileNrPath
}

// Export implements plugin.Configurator interface.
func (p *Plugin) Export(key string, params []string, _ plugin.ContextProvider) (any, error) {
	if len(params) > 0 {
		return nil, zbxerr.ErrorTooManyParameters
	}

	switch key {
	case "kernel.maxproc", "kernel.maxfiles", "kernel.openfiles":
		return p.gatherData(key)
	default:
		/* SHOULD_NEVER_HAPPEN */
		return 0, plugin.UnsupportedMetricError
	}
}
