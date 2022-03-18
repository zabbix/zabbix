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

package main

import (
	"fmt"
	"strings"

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
	// counter int
	options Options
}

var impl Plugin

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

func init() {
	plugin.RegisterMetrics(&impl, "DebugFull", "debug.external.full", "Returns test value.")
}
