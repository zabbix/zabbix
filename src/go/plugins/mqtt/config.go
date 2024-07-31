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

package mqtt

import (
	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

type Options struct {
	Timeout int `conf:"optional,range=1:30"`
	// Sessions stores pre-defined named sets of connections settings.
	Sessions map[string]Session `conf:"optional"`
	// Default stores default connection parameter values from configuration file
	Default *Session `conf:"optional"`
}

type Session struct {
	URL         string `conf:"name=Url,optional"`
	Topic       string `conf:"optional"`
	Password    string `conf:"optional"`
	User        string `conf:"optional"`
	TLSCAFile   string `conf:"name=TLSCAFile,optional"`
	TLSCertFile string `conf:"name=TLSCertFile,optional"`
	TLSKeyFile  string `conf:"name=TLSKeyFile,optional"`
}

func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	if err := conf.Unmarshal(options, &p.options, true); err != nil {
		p.Warningf("cannot unmarshal configuration options: %s", err)
	}

	if p.options.Timeout == 0 {
		p.options.Timeout = global.Timeout
	}
}

func (p *Plugin) Validate(options interface{}) error {
	var o Options

	err := conf.Unmarshal(options, &o, true)
	if err != nil {
		return errs.Errorf("plugin config validation failed, %s", err.Error())
	}

	return nil
}
