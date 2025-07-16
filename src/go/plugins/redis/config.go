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

package redis

import (
	"fmt"

	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

var _ plugin.Configurator = (*Plugin)(nil)

type Session struct {
	URI      string `conf:"name=Uri,optional"`
	Password string `conf:"optional"`
	User     string `conf:"optional"`

	TLSConnect    string `conf:"name=TLSConnect,optional"`
	TLSCAFile     string `conf:"name=TLSCAFile,optional"`
	TLSServerName string `conf:"name=TLSServerName,optional"`
	TLSCertFile   string `conf:"name=TLSCertFile,optional"`
	TLSKeyFile    string `conf:"name=TLSKeyFile,optional"`
}

type PluginOptions struct {
	// Timeout is the maximum time for waiting when a request has to be done. Default value equals the global timeout.
	Timeout int `conf:"optional,range=1:30"`

	// KeepAlive is a time to wait before unused connections will be closed.
	KeepAlive int `conf:"optional,range=60:900,default=300"`

	// Sessions stores pre-defined named sets of connections settings.
	Sessions map[string]Session `conf:"optional"`

	// Default stores default connection parameter values from configuration file
	Default Session `conf:"optional"`
}

// Configure implements the Configurator interface.
// Initializes configuration structures.
func (p *Plugin) Configure(global *plugin.GlobalOptions, options any) {
	err := conf.UnmarshalStrict(options, &p.options)
	if err != nil {
		p.Errf("cannot unmarshal configuration options: %s", err)
	}

	if p.options.Timeout == 0 {
		p.options.Timeout = global.Timeout
	}
}

// Validate implements the Configurator interface.
// Returns an error if validation of a plugin's configuration is failed.
func (p *Plugin) Validate(options any) error {
	var (
		opts PluginOptions
		err  error
	)

	err = conf.UnmarshalStrict(options, &opts)
	if err != nil {
		return errs.Wrap(err, "plugin config validation failed")
	}

	//validating only TLS on default options
	err = validateTLSConfiguration(opts.Default)
	if err != nil {
		return errs.Wrap(err, "plugin config validation failed on default TLS configuration")
	}

	for k, v := range opts.Sessions {
		err = validateSession(v)
		if err != nil {
			return errs.Wrap(err, fmt.Sprintf("plugin config validation failed on session %s", k))
		}
	}

	return err
}
