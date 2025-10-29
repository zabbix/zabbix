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

package smart

import (
	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

// Options holds plugin options.
type Options struct {
	Timeout int    `conf:"optional,range=1:30"`
	Path    string `conf:"optional"`
}

// Configure loads in plugin config files.
func (p *Plugin) Configure(global *plugin.GlobalOptions, options any) {
	if err := conf.UnmarshalStrict(options, &p.options); err != nil {
		p.Errf("cannot unmarshal configuration options: %s", err)
	}

	if p.options.Timeout == 0 {
		p.options.Timeout = global.Timeout
	}

	p.ctl = NewSmartCtl(p.Logger, p.options.Path, p.options.Timeout)
}

// Validate validates plugin config file.
func (p *Plugin) Validate(options any) error { //nolint:revive
	var o Options

	err := conf.UnmarshalStrict(options, &o)
	if err != nil {
		return errs.Wrap(err, "plugin config validation failed")
	}

	return nil
}
