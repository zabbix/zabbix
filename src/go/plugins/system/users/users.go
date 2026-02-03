/*
** Zabbix
** Copyright (C) 2001-2026 Zabbix SIA
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

package users

import (
	"golang.zabbix.com/agent2/pkg/zbxcmd"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

var impl Plugin

type Plugin struct {
	plugin.Base
	options  Options
	executor zbxcmd.Executor
}

type Options struct {
	Timeout int `conf:"optional,range=1:30"`
}

func init() {
	err := plugin.RegisterMetrics(&impl, "Users", "system.users.num", "Returns number of users logged in.")
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

// Configure loads options.
func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	p.options.Timeout = global.Timeout
}

// Validate empty validate so the plugin implements the configurator.
func (*Plugin) Validate(_ any) error {
	return nil
}

// Export -
func (p *Plugin) Export(_ string, params []string, ctx plugin.ContextProvider) (any, error) {
	if len(params) > 0 {
		return nil, errs.New("too many parameters")
	}

	result, err := p.getUsersNum()
	if err != nil {
		return nil, errs.Errorf("failed to get logged in user count: %s", err.Error())
	}

	return result, nil
}
