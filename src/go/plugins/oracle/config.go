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

package oracle

import (
	"path/filepath"
	"strconv"

	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin"
)

// Session type contains session parameters of the config file.
type Session struct {
	// URI defines an address of the Oracle Net Listener.
	URI string `conf:"name=Uri,optional"`

	Password string `conf:"optional"`

	User string `conf:"optional"`

	// Service name that identifies a database instance
	Service string `conf:"optional"`

	ConnectionTimeout string `conf:"name=ConnectionTimeout,optional"`
}

// PluginOptions option from the config file.
type PluginOptions struct {
	// Deprecated old timeout value kept for compatibility.
	LegacyConnectionTimeout int `conf:"name=ConnectTimeout,optional,range=1:30"`

	// Deprecated old timeout value kept for compatibility.
	LegacyItemTimeout int `conf:"name=CallTimeout,optional,range=1:30"`

	// KeepAlive is a time to wait before unused connections will be closed.
	KeepAlive int `conf:"optional,range=60:900,default=300"`

	// Sessions stores pre-defined named sets of connection settings.
	Sessions map[string]Session `conf:"optional"`

	// CustomQueriesPath is a full pathname of a directory containing *.sql files with custom queries.
	CustomQueriesPath string `conf:"optional"`

	// CustomQueriesEnabled enables custom query key.
	CustomQueriesEnabled bool `conf:"optional,default=false"`

	// ResolveTNS enables the interpretation of a connection string (ConnString) in a metrics key as TNS.
	ResolveTNS bool `conf:"optional,default=true"`

	// Default stores default connection parameter values from configuration file
	Default Session `conf:"optional"`
}

// Configure implements the Configurator interface.
// Initializes configuration structures.
func (p *Plugin) Configure(global *plugin.GlobalOptions, options any) {
	if err := conf.UnmarshalStrict(options, &p.options); err != nil {
		p.Errf("cannot unmarshal configuration options: %s", err)
	}

	p.options.setCustomQueriesPathDefault()

	if p.options.LegacyConnectionTimeout != 0 {
		log.Debugf("'Plugins.Oracle.ConnectTimeout' is deprecated")

		if p.options.Default.ConnectionTimeout == "" {
			p.options.Default.ConnectionTimeout = strconv.Itoa(p.options.LegacyConnectionTimeout)
		}
	}

	if p.options.LegacyItemTimeout != 0 {
		log.Debugf("'Plugins.Oracle.CallTimeout' is deprecated")
	}

	if p.options.Default.ConnectionTimeout == "" {
		p.options.Default.ConnectionTimeout = strconv.Itoa(global.Timeout)
	}

	if p.options.LegacyConnectionTimeout == 0 {
		p.options.LegacyConnectionTimeout = global.Timeout
	}

	if p.options.LegacyItemTimeout == 0 {
		p.options.LegacyItemTimeout = global.Timeout
	}
}

// Validate implements the Configurator interface.
// Returns an error if validation of a plugin's configuration is failed.
func (p *Plugin) Validate(options any) error { //nolint:revive
	var opts PluginOptions

	err := conf.UnmarshalStrict(options, &opts)
	if err != nil {
		return errs.Wrap(err, "failed to unmarshal configuration options")
	}

	for k, s := range opts.Sessions {
		if s.ConnectionTimeout != "" {
			ct, err := strconv.Atoi(s.ConnectionTimeout)
			if err != nil {
				return errs.Errorf(
					"connection timeout '%v' must be an integer for session %s",
					s.ConnectionTimeout,
					k,
				)
			}

			if ct < 1 || ct > 30 {
				return errs.Errorf(
					"connection timeout '%v' for session %s must be between 1 and 30",
					s.ConnectionTimeout,
					k,
				)
			}
		}
	}

	if opts.Default.ConnectionTimeout != "" {
		t, err := strconv.Atoi(opts.Default.ConnectionTimeout)
		if err != nil {
			return errs.Errorf(
				"default connection timeout '%v' must be an integer",
				opts.Default.ConnectionTimeout,
			)
		}

		if t < 1 || t > 30 {
			return errs.Errorf(
				"default connection timeout '%v' must be between 1 and 30",
				opts.Default.ConnectionTimeout,
			)
		}
	}

	if opts.CustomQueriesEnabled && opts.CustomQueriesPath != "" && !filepath.IsAbs(opts.CustomQueriesPath) {
		return errs.Errorf("opto.CustomQueriesPath path: '%s' must be absolute", opts.CustomQueriesPath)
	}

	return nil
}
