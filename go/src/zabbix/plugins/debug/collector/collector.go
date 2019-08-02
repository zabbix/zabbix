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
	"zabbix/internal/plugin"
	"zabbix/pkg/std"
)

// Plugin -
type Plugin struct {
	plugin.Base
	interval int
	counter  int
}

var impl Plugin
var stdOs std.Os

func (p *Plugin) Export(key string, params []string) (result interface{}, err error) {
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
	if val, ok := options["Interval"]; ok {
		p.interval, _ = strconv.Atoi(val)
	} else {
		p.interval = 10
	}
}

func init() {
	stdOs = std.NewOs()
	impl.interval = 1
	plugin.RegisterMetric(&impl, "debugcollector", "debug.collector", "Returns empty value")
}
