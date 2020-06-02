/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package postgres

import (
	"fmt"

	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/plugin"
)

// Session struct holds individual options for postgres connection for each session
type Session struct {

	// Path of  Postgres server. Default is localhost.
	Host string `conf:"optional"`

	// Port of  Postgres server. Default 5432.
	Port uint16 `conf:"optional"`

	//  Database of  Postgres server.
	Database string `conf:"optional"`

	// User of  Postgres server.
	User string `conf:"optional"`

	// Password to send to protected Postgres server.
	Password string `conf:"optional"`
}

// PluginOptions are options for Postgres connection
type PluginOptions struct {

	// Host is the default host.
	Host string `conf:"default=localhost"`

	// Port is the default PORT	.
	Port uint16 `conf:"range=1024:65535,default=5432"`

	// Database is the default DB name.
	Database string `conf:"default=postgres"`

	// User is the default user for postgres.
	User string `conf:"default=postgres"`

	// Password is the default password.
	Password string `conf:"default=postgres"`

	// Timeout is the maximum time for waiting when a request has to be done. Default value equals the global timeout which is 3.
	Timeout int `conf:"optional,range=1:30"`

	// KeepAlive is a time to wait before unused connections will be closed.
	KeepAlive int64 `conf:"optional,range=60:900,default=300"`

	// Sessions stores pre-defined named sets of connections settings.
	Sessions map[string]*Session `conf:"optional"`
}

const (
	DefaultPort    = 5432
	MaxAuthPassLen = 512
)

// Configure implements the Configurator interface.
// Initializes configuration structures.
func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {

	if err := conf.Unmarshal(options, &p.options); err != nil {
		p.Errf("cannot unmarshal configuration options: %s", err)
	}

	// if no Timeout was given throught options interface
	if p.options.Timeout == 0 {
		p.options.Timeout = global.Timeout
	}

	for _, session := range p.options.Sessions {
		if session.Host == "" {
			session.Host = p.options.Host
		}
		if session.Port == 0 {
			session.Port = p.options.Port
		}
		if session.User == "" {
			session.User = p.options.User
		}
		if session.Database == "" {
			session.Database = p.options.Database
		}
		if session.Password == "" {
			session.Password = p.options.Password
		}
	}
}

// Validate implements the Configurator interface.
// Returns an error if validation of a plugin's configuration is failed.
func (p *Plugin) Validate(options interface{}) error {
	var opts PluginOptions
	var err error

	err = conf.Unmarshal(options, &opts)
	if err != nil {
		return err
	}
	// validate each parameter of PostgreSQL connection
	// check if localhost or addr
	err = validateHost(opts.Host)
	if err != nil {
		return err
	}

	// validate Database name
	err = validateDatabase(opts.Database)
	if err != nil {
		return err
	}
	// validate user name for Postgres
	err = validateUser(opts.User)
	if err != nil {
		return err
	}

	if len(opts.Password) > MaxAuthPassLen {
		return fmt.Errorf("password cannot be longer than %d characters", MaxAuthPassLen)
	}

	user := opts.User
	database := opts.Database
	host := opts.Host

	for name, session := range opts.Sessions {

		if session.Host != "" {
			host = session.Host
		}
		if session.Database != "" {
			database = session.Database
		}
		if session.User != "" {
			user = session.User
		}
		// validate each parameter of PostgreSQL connection
		// check if localhost or addr
		err = validateHost(host)
		if err != nil {
			return fmt.Errorf("invalid host parameters for session '%s': %s", host, err.Error())
		}
		// validate Database name
		err = validateDatabase(database)
		if err != nil {
			return fmt.Errorf("invalid database parameters for session '%s': %s", name, err.Error())
		}
		// validate user name for Postgres
		err = validateUser(user)
		if err != nil {
			return fmt.Errorf("invalid user parameters for session '%s': %s", name, err.Error())
		}
		// validate Password length
		if len(session.Password) > MaxAuthPassLen {
			return fmt.Errorf("invalid parameters for session '%s': password cannot be longer than %d characters",
				name, MaxAuthPassLen)
		}

	}

	return err
}
