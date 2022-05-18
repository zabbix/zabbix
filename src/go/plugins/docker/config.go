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

package docker

import (
	"errors"
	"strings"

	"git.zabbix.com/ap/plugin-support/conf"
	"git.zabbix.com/ap/plugin-support/plugin"
)

// Options is a plugin configuration
type Options struct {
	plugin.SystemOptions `conf:"optional"`
	Endpoint             string `conf:"default=unix:///var/run/docker.sock"`
	Timeout              int    `conf:"optional,range=1:30"`
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

	socketPath := strings.Split(p.options.Endpoint, "://")[1]
	p.client = newClient(socketPath, p.options.Timeout)
}

// Validate implements the Configurator interface.
// Returns an error if validation of a plugin's configuration is failed.
func (p *Plugin) Validate(options interface{}) error {
	var opts Options

	err := conf.Unmarshal(options, &opts)
	if err != nil {
		return err
	}

	endpointParts := strings.SplitN(opts.Endpoint, "://", 2)
	if len(endpointParts) == 1 || endpointParts[0] != "unix" {
		return errors.New("invalid endpoint format")
	}

	return err
}
