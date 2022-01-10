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

package oracle

import (
	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/plugin"
)

type Session struct {
	// URI defines an address of the Oracle Net Listener.
	URI string `conf:"name=Uri,optional"`

	Password string `conf:"optional"`

	User string `conf:"optional"`

	// Service name that identifies a database instance
	Service string `conf:"optional"`
}

type PluginOptions struct {
	// ConnectTimeout is the maximum time in seconds for waiting when a connection has to be established.
	// Default value equals to the global timeout.
	ConnectTimeout int `conf:"optional,range=1:30"`

	// CallTimeout is the maximum time in seconds for waiting when a request has to be done.
	// Default value equals to the global agent timeout.
	CallTimeout int `conf:"optional,range=1:30"`

	// KeepAlive is a time to wait before unused connections will be closed.
	KeepAlive int `conf:"optional,range=60:900,default=300"`

	// Sessions stores pre-defined named sets of connections settings.
	Sessions map[string]Session `conf:"optional"`

	// CustomQueriesPath is a full pathname of a directory containing *.sql files with custom queries.
	CustomQueriesPath string `conf:"optional"`
}

// Configure implements the Configurator interface.
// Initializes configuration structures.
func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	if err := conf.Unmarshal(options, &p.options); err != nil {
		p.Errf("cannot unmarshal configuration options: %s", err)
	}

	if p.options.ConnectTimeout == 0 {
		p.options.ConnectTimeout = global.Timeout
	}

	if p.options.CallTimeout == 0 {
		p.options.CallTimeout = global.Timeout
	}
}

// Validate implements the Configurator interface.
// Returns an error if validation of a plugin's configuration is failed.
func (p *Plugin) Validate(options interface{}) error {
	var opts PluginOptions

	return conf.Unmarshal(options, &opts)
}
