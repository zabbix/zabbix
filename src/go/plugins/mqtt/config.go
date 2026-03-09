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

package mqtt

import (
	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin"
)

var _ plugin.Configurator = (*Plugin)(nil)

type options struct {
	LegacyTimeout int `conf:"name=Timeout,optional,range=1:30"`
	// Sessions stores pre-defined named sets of connections settings.
	Sessions map[string]session `conf:"optional"`
	// Default stores default connection parameter values from configuration file
	Default *session `conf:"optional"`
}

type session struct {
	URL               string `conf:"name=Url,optional"`
	Topic             string `conf:"optional"`
	Password          string `conf:"optional"`
	User              string `conf:"optional"`
	TLSCAFile         string `conf:"name=TLSCAFile,optional"`
	TLSCertFile       string `conf:"name=TLSCertFile,optional"`
	TLSKeyFile        string `conf:"name=TLSKeyFile,optional"`
	ConnectionTimeout int    `conf:"optional,range=1:30" json:"ConnectionTimeout,string"`
}

// Configure implements plugin.Configurator methods.
func (p *Plugin) Configure(global *plugin.GlobalOptions, options any) {
	err := conf.UnmarshalStrict(options, &p.options)
	if err != nil {
		p.Warningf("cannot unmarshal configuration options: %s", err)
	}

	if p.options.LegacyTimeout != 0 {
		log.Debugf("[MQTT plugin] DEBUG: Config value 'Plugins.MQTT.Timeout' is deprecated, use 'Plugins.MQTT.Default.ConnectionTimeout'' instead.")

		if p.options.Default.ConnectionTimeout != 0 {
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

// Validate implements plugin.Configurator methods.
func (*Plugin) Validate(opts any) error {
	var o options

	err := conf.UnmarshalStrict(opts, &o)
	if err != nil {
		return errs.Wrap(err, "plugin config validation failed")
	}

	return nil
}
