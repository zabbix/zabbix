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
	"errors"
	"fmt"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

var impl Plugin

type Plugin struct {
	plugin.Base
}

func init() {
	err := plugin.RegisterMetrics(&impl, "Users", "system.users.num", "Returns number of useres logged in.")
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
}

func (p *Plugin) Validate(options interface{}) error {
	return nil
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if len(params) > 0 {
		return nil, errors.New("Too many parameters.")
	}

	result, err = p.getUsersNum(ctx.Timeout())
	if err != nil {
		return nil, fmt.Errorf("Failed to get logged in user count: %s.", err.Error())
	}

	return
}
