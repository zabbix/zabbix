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

package empty

import (
	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

var impl Plugin

type Options struct {
	Interval int
}

// Plugin -
type Plugin struct {
	plugin.Base
	counter int
	options Options
}

func init() {
	impl.options.Interval = 1
	err := plugin.RegisterMetrics(&impl, "DebugCollector", "debug.collector", "Returns empty value.")
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

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
	if err := conf.Unmarshal(private, &p.options, true); err != nil {
		p.Warningf("cannot unmarshal configuration options: %s", err)
	}
	p.Debugf("configure: interval=%d", p.options.Interval)
}

func (p *Plugin) Validate(private interface{}) (err error) {
	return
}
