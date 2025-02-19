/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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

	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

var impl Plugin

// Options holds plugin options.
type Options struct {
	Interval int
}

// Plugin main plugin structure holding all the required data for plugin to operate over multiple exports.
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

// Export returns plugin metric data.
//
//nolint:unparam // used for unused error parameter, which is needed for Exporter interface.
func (p *Plugin) Export(key string, params []string, _ plugin.ContextProvider) (any, error) {
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

// Configure loads the plugin configuration.
func (p *Plugin) Configure(_ *plugin.GlobalOptions, private any) {
	err := conf.UnmarshalStrict(private, &p.options)
	if err != nil {
		p.Warningf("cannot unmarshal configuration options: %s", err)
	}

	p.Debugf("configure: interval=%d", p.options.Interval)
}

// Validate validates the plugin configuration.
func (p *Plugin) Validate(private any) error {
	p.Debugf("executing Validate")

	err := conf.UnmarshalStrict(private, &p.options)
	if err != nil {
		return errs.Wrap(err, "plugin config validation failed")
	}

	return nil
}

// Start is run when plugin is started, useful for initialization of requirements.
func (p *Plugin) Start() {
	p.Debugf("executing Start")
}

// Stop is run when plugin is started, useful for clean up.
func (p *Plugin) Stop() {
	p.Debugf("executing Stop")
}
