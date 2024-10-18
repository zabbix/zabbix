/*
** Copyright (C) 2001-2024 Zabbix SIA
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
	"golang.zabbix.com/sdk/conf"
)

type Session struct {
	// URI defines an address of the Oracle Net Listener.
	URI string `conf:"name=Uri,optional"`

	Password string `conf:"optional"`

	User string `conf:"optional"`

	// Service name that identifies a database instance
	Service string `conf:"optional"`
}

// Validate implements the Configurator interface.
// Returns an error if validation of a plugin's configuration is failed.
func (p *Plugin) Validate(options interface{}) error {
	var opts PluginOptions

	return conf.Unmarshal(options, &opts)
}
