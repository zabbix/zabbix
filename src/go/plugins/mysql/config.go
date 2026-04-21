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

package mysql

import (
	"path/filepath"

	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin"
)

// Session is a general structure for storing sessions' configuration.
type Session struct {
	URI         string `conf:"name=Uri,optional"`
	Password    string `conf:"optional"`
	User        string `conf:"optional"`
	TLSConnect  string `conf:"name=TLSConnect,optional"`
	TLSCAFile   string `conf:"name=TLSCAFile,optional"`
	TLSCertFile string `conf:"name=TLSCertFile,optional"`
	TLSKeyFile  string `conf:"name=TLSKeyFile,optional"`

	// json tag is a temporary workaround until metric.SetDefaults() supports integers
	ConnectionTimeout int `conf:"optional,range=1:30" json:"ConnectionTimeout,string"` //nolint:tagalign,tagliatelle
}

// PluginOptions option from config file.
type PluginOptions struct {
	// Deprecated old timeout value kept for compatibility.
	LegacyConnectionTimeout int `conf:"name=Timeout,optional,range=1:30"`

	// Deprecated old timeout value kept for compatibility.
	LegacyItemTimeout int `conf:"name=CallTimeout,optional,range=1:30"`

	// KeepAlive is a time to wait before unused connections will be closed.
	KeepAlive int `conf:"optional,range=60:900,default=300"`

	// Sessions stores pre-defined named sets of connections settings.
	Sessions map[string]Session `conf:"optional"`

	// CustomQueriesPath is a full pathname of a directory containing *.sql files with custom queries.
	CustomQueriesPath string `conf:"optional"`

	// CustomQueriesEnabled disabled or enabled custom query functionality.
	CustomQueriesEnabled bool `conf:"optional,default=false"`

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
		log.Debugf("'Plugins.Mysql.Timeout' is deprecated")

		if p.options.Default.ConnectionTimeout == 0 {
			p.options.Default.ConnectionTimeout = p.options.LegacyConnectionTimeout
		}
	}

	if p.options.LegacyItemTimeout != 0 {
		log.Debugf("'Plugins.Mysql.CallTimeout' is deprecated")
	}

	if p.options.Default.ConnectionTimeout == 0 {
		p.options.Default.ConnectionTimeout = global.Timeout
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
func (*Plugin) Validate(options any) error {
	var opts PluginOptions

	err := conf.UnmarshalStrict(options, &opts)
	if err != nil {
		return errs.Wrap(err, "failed to unmarshal configuration options")
	}

	if opts.CustomQueriesEnabled && opts.CustomQueriesPath != "" && !filepath.IsAbs(opts.CustomQueriesPath) {
		return errs.Errorf("opto.CustomQueriesPath path: '%s' must be absolute", opts.CustomQueriesPath)
	}

	return nil
}
