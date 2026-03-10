/*
** Copyright (C) 2001-2026 Zabbix SIA
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

package redis

import (
	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin"
)

var _ plugin.Configurator = (*Plugin)(nil)

type pluginOptions struct {
	// Deprecated old timeout value kept for compatibility.
	LegacyTimeout int `conf:"name=Timeout,optional,range=1:30"`

	// KeepAlive is a time to wait before unused connections will be closed.
	KeepAlive int `conf:"optional,range=60:900,default=300"`

	// Sessions stores pre-defined named sets of connections settings.
	Sessions map[string]session `conf:"optional"`

	// Default stores default connection parameter values from configuration file
	Default session `conf:"optional"`
}

// Configure implements the Configurator interface.
// Initializes configuration structures.
func (p *Plugin) Configure(global *plugin.GlobalOptions, options any) {
	err := conf.UnmarshalStrict(options, &p.options)
	if err != nil {
		p.Errf("cannot unmarshal configuration options: %s", err)
	}

	if p.options.LegacyTimeout != 0 {
		log.Debugf("[%s] Config value 'Plugins.Redis.Timeout' is deprecated. Use 'Plugins.Redis.Default.ConnectionTimeout' instead.", pluginName)

		if p.options.Default.ConnectionTimeout == 0 {
			p.options.Default.ConnectionTimeout = p.options.LegacyTimeout
		}
	}

	if p.options.Default.ConnectionTimeout == 0 {
		p.options.Default.ConnectionTimeout = global.Timeout
	}

	if p.options.LegacyTimeout == 0 {
		p.options.LegacyTimeout = global.Timeout
	}
}

// Validate implements the Configurator interface.
// Returns an error if validation of a plugin's configuration is failed.
func (*Plugin) Validate(options any) error {
	var (
		opts pluginOptions
	)

	err := conf.UnmarshalStrict(options, &opts)
	if err != nil {
		return errs.Wrap(err, "plugin config validation failed")
	}

	err = opts.Default.runSourceConsistencyValidation()
	if err != nil {
		return errs.Wrap(err, "invalid 'Default' configuration")
	}

	// Reuse the existing resolver to validate the default configuration's TLSConnect value.
	// This keeps all validation and resolution logic centralized within a single function, albeit with a double check.
	_, err = opts.Default.resolveTLSConnect(&opts.Default)
	if err != nil {
		return errs.Wrap(err, "invalid 'Default' configuration")
	}

	for sessionName, s := range opts.Sessions {
		err = s.validateSession(&opts.Default)
		if err != nil {
			return errs.Wrap(err, "invalid session '"+sessionName+"' configuration")
		}
	}

	return nil
}
