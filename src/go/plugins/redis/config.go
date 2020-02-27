/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

package redis

import (
	"fmt"
	"time"
	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/plugin"
)

type Session struct {
	// URI is a connection string consisting of a network scheme, a host address and a port or a path to a Unix-socket.
	Uri string `conf:"optional"`

	// Password to send to protected Redis server.
	Password string `conf:"optional"`
}

type PluginOptions struct {
	// URI is the default connection string.
	Uri string `conf:"default=tcp://localhost:6379"`

	// Password is the default password.
	Password string `conf:"optional"`

	// Timeout is the maximum time for waiting when a request has to be done. Default value equals the global timeout.
	Timeout int `conf:"optional,range=1:30"`

	// KeepAlive is a time to wait before unused connections will be closed.
	KeepAlive int `conf:"optional,range=60:900,default=300"`

	// Sessions stores pre-defined named sets of connections settings.
	Sessions map[string]*Session `conf:"optional"`
}

// Configure implements the Configurator interface.
// Initializes configuration structures.
func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	if err := conf.Unmarshal(options, &p.options); err != nil {
		p.Errf("cannot unmarshal configuration options: %s", err)
	}
	if p.options.Timeout == 0 {
		p.options.Timeout = global.Timeout
	}

	for _, session := range p.options.Sessions {
		if session.Uri == "" {
			session.Uri = p.options.Uri
		}
	}

	p.connMgr = NewConnManager(
		time.Duration(p.options.KeepAlive)*time.Second,
		time.Duration(p.options.Timeout)*time.Second)
}

const MaxAuthPassLen = 512

// Validate implements the Configurator interface.
// Returns an error if validation of a plugin's configuration is failed.
func (p *Plugin) Validate(options interface{}) error {
	var opts PluginOptions
	var err error

	err = conf.Unmarshal(options, &opts)
	if err != nil {
		return err
	}

	err = validateUri(opts.Uri)
	if err != nil {
		return err
	}

	if len(opts.Password) > MaxAuthPassLen {
		return fmt.Errorf("password cannot be longer than %d characters", MaxAuthPassLen)
	}

	uri := opts.Uri
	for name, session := range opts.Sessions {
		if session.Uri != "" {
			uri = session.Uri
		}

		err = validateUri(uri)
		if err != nil {
			return fmt.Errorf("invalid parameters for session '%s': %s", name, err.Error())
		}

		if len(session.Password) > MaxAuthPassLen {
			return fmt.Errorf("invalid parameters for session '%s': password cannot be longer than %d characters",
				name, MaxAuthPassLen)
		}
	}

	return err
}
