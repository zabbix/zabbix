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

package systemrun

import (
	"fmt"
	"time"

	"git.zabbix.com/ap/plugin-support/conf"
	"git.zabbix.com/ap/plugin-support/plugin"
	"zabbix.com/internal/agent"
	"zabbix.com/pkg/zbxcmd"
)

type Options struct {
	plugin.SystemOptions `conf:"optional,name=System"`
	Timeout              int `conf:"optional,range=1:30"`
	LogRemoteCommands    int `conf:"optional,range=0:1,default=0"`
}

// Plugin -
type Plugin struct {
	plugin.Base
	options Options
}

var impl Plugin

func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	if err := conf.Unmarshal(options, &p.options); err != nil {
		p.Warningf("cannot unmarshal configuration options: %s", err)
	}
	if p.options.Timeout == 0 {
		p.options.Timeout = global.Timeout
	}
}

func (p *Plugin) Validate(options interface{}) error {
	var o Options
	return conf.Unmarshal(options, &o)
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if len(params) > 2 {
		return nil, fmt.Errorf("Too many parameters.")
	}

	if len(params) == 0 || len(params[0]) == 0 {
		return nil, fmt.Errorf("Invalid first parameter.")
	}

	if p.options.LogRemoteCommands == 1 && ctx.ClientID() != agent.LocalChecksClientID {
		p.Warningf("Executing command:'%s'", params[0])
	} else {
		p.Debugf("Executing command:'%s'", params[0])
	}

	if len(params) == 1 || params[1] == "" || params[1] == "wait" {
		stdoutStderr, err := zbxcmd.Execute(params[0], time.Second*time.Duration(p.options.Timeout), "")
		if err != nil {
			return nil, err
		}

		p.Debugf("command:'%s' length:%d output:'%.20s'", params[0], len(stdoutStderr), stdoutStderr)

		return stdoutStderr, nil
	} else if params[1] == "nowait" {
		err := zbxcmd.ExecuteBackground(params[0])

		if err != nil {
			return nil, err
		}

		return 1, nil
	}

	return nil, fmt.Errorf("Invalid second parameter.")
}

func init() {
	plugin.RegisterMetrics(&impl, "SystemRun", "system.run", "Run specified command.")
}
