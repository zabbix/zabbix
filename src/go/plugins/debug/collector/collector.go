/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

package empty

import (
	"strconv"

	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/std"
)

// Plugin -
type Plugin struct {
	plugin.Base
	interval int
	counter  int
}

var impl Plugin
var stdOs std.Os

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	p.Debugf("export %s%v", key, params)
	return p.counter, nil
}

func (p *Plugin) Collect() error {
	p.Debugf("collect")
	p.counter++
	return nil
}

func (p *Plugin) Period() int {
	return p.interval
}

func (p *Plugin) Configure(options map[string]string) {
	p.Debugf("configure")
	p.interval = 10
	if options != nil {
		if val, ok := options["Interval"]; ok {
			p.interval, _ = strconv.Atoi(val)
		}
	}
}

func init() {
	stdOs = std.NewOs()
	impl.interval = 1
	plugin.RegisterMetrics(&impl, "DebugCollector", "debug.collector", "Returns empty value.")
}
