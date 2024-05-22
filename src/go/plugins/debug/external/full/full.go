/*
** Copyright (C) 2001-2024 Zabbix SIA
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

package main

import (
	"fmt"
	"strings"

	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

var impl Plugin

type Options struct {
	plugin.SystemOptions `conf:"optional,name=System"`
	Interval             int
}

// Plugin -
type Plugin struct {
	plugin.Base
	// counter int
	options Options
}

func init() {
	err := plugin.RegisterMetrics(&impl, "DebugFull", "debug.external.full", "Returns test value.")
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	p.Debugf("export %s%v, with interval: %d", key, params, p.options.Interval)

	if len(params) == 0 {
		return "debug full test response, without parameters", nil
	}

	var out string

	for _, p := range params {
		out += p + " "
	}

	out = strings.TrimSpace(out)

	return fmt.Sprintf("debug full test response, with parameters: %s", out), nil
}

func (p *Plugin) Configure(global *plugin.GlobalOptions, private interface{}) {
	if err := conf.Unmarshal(private, &p.options); err != nil {
		p.Warningf("cannot unmarshal configuration options: %s", err)
	}
	p.Debugf("configure: interval=%d", p.options.Interval)
}

func (p *Plugin) Validate(private interface{}) (err error) {
	p.Debugf("executing Validate")
	err = conf.Unmarshal(private, &p.options)
	return
}

func (p *Plugin) Start() {
	p.Debugf("executing Start")
}

func (p *Plugin) Stop() {
	p.Debugf("executing Stop")
}
