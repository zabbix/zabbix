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

package users

import (
	"golang.zabbix.com/agent2/pkg/zbxcmd"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

var impl Plugin

type Plugin struct {
	plugin.Base
	executor zbxcmd.Executor
}

func init() {
	err := plugin.RegisterMetrics(&impl, "Users", "system.users.num", "Returns number of users logged in.")
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

// Configure empty configure so the plugin implements the configurator.
func (*Plugin) Configure(_ *plugin.GlobalOptions, _ any) {}

// Validate empty validate so the plugin implements the configurator.
func (*Plugin) Validate(_ any) error {
	return nil
}

// Export -
func (p *Plugin) Export(_ string, params []string, ctx plugin.ContextProvider) (any, error) {
	if len(params) > 0 {
		return nil, errs.New("too many parameters")
	}

	result, err := p.getUsersNum(ctx.Timeout())
	if err != nil {
		return nil, errs.Errorf("failed to get logged in user count: %s", err.Error())
	}

	return result, nil
}
