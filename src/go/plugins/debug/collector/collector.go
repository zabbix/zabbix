/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/plugin"
)

type Options struct {
	plugin.SystemOptions `conf:"optional,name=System"`
	Interval             int
}

// Plugin -
type Plugin struct {
	plugin.Base
	counter int
	options Options
}

var impl Plugin

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
	p.Debugf("period: interval=%d", p.options.Interval)
	return p.options.Interval
}

func (p *Plugin) Configure(global *plugin.GlobalOptions, private interface{}) {
	p.options.Interval = 10
	if err := conf.Unmarshal(private, &p.options); err != nil {
		p.Warningf("cannot unmarshal configuration options: %s", err)
	}
	p.Debugf("configure: interval=%d", p.options.Interval)
}

func (p *Plugin) Validate(private interface{}) (err error) {
	return
}

func init() {
	impl.options.Interval = 1
	plugin.RegisterMetrics(&impl, "DebugCollector", "debug.collector", "Returns empty value.")
}
