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

package memcached

import (
	"fmt"
	"strconv"

	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin"
)

type session struct {
	URI      string `conf:"name=Uri,optional"`
	Password string `conf:"optional"`
	User     string `conf:"optional"`

	ConnectionTimeout string `conf:"name=ConnectionTimeout,optional"`
}

type PluginOptions struct {
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
func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	if err := conf.UnmarshalStrict(options, &p.options); err != nil {
		p.Errf("cannot unmarshal configuration options: %s", err)
	}

	if p.options.LegacyTimeout != 0 {
		log.Debugf("'Plugins.Memcached.Timeout' is deprecated")

		if p.options.Default.ConnectionTimeout == "" {
			p.options.Default.ConnectionTimeout = strconv.Itoa(p.options.LegacyTimeout)
		}
	}

	if p.options.Default.ConnectionTimeout == "" {
		p.options.Default.ConnectionTimeout = strconv.Itoa(global.Timeout)
	}

	if p.options.LegacyTimeout == 0 {
		p.options.LegacyTimeout = global.Timeout
	}
}

// Validate implements the Configurator interface.
// Returns an error if validation of a plugin's configuration is failed.
//
//nolint:gocyclo,cyclop // will be removed once set defaults can handle ints
func (*Plugin) Validate(options any) error {
	var (
		opts PluginOptions
		ct   int
	)

	err := conf.UnmarshalStrict(options, &opts)
	if err != nil {
		return err
	}

	for name, session := range opts.Sessions {
		if len(session.Password+session.User) > maxEntryLen {
			return fmt.Errorf("invalid parameters for session '%s': credentials cannot be longer "+
				"than %d characters", name, maxEntryLen)
		}

		if session.ConnectionTimeout != "" {
			ct, err = strconv.Atoi(session.ConnectionTimeout)
			if err != nil {
				return errs.Errorf(
					"connection timeout '%v' must be an integer for session %s",
					session.ConnectionTimeout,
					name,
				)
			}

			if ct < 1 || ct > 30 {
				return errs.Errorf(
					"connection timeout '%v' for session %s must be between 1 and 30",
					session.ConnectionTimeout,
					name,
				)
			}
		}
	}

	if opts.Default.ConnectionTimeout != "" {
		ct, err = strconv.Atoi(opts.Default.ConnectionTimeout)
		if err != nil {
			return errs.Errorf(
				"default connection timeout '%v' must be an integer",
				opts.Default.ConnectionTimeout,
			)
		}

		if ct < 1 || ct > 30 {
			return errs.Errorf(
				"default connection timeout '%v' must be between 1 and 30",
				opts.Default.ConnectionTimeout,
			)
		}
	}

	return err
}
