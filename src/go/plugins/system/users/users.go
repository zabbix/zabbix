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

package users

import (
	"errors"
	"fmt"

	"git.zabbix.com/ap/plugin-support/plugin"
)

type Plugin struct {
	plugin.Base
	options Options
}

type Options struct {
	plugin.SystemOptions `conf:"optional,name=System"`
	Timeout              int `conf:"optional,range=1:30"`
}

var impl Plugin

func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	p.options.Timeout = global.Timeout
}

func (p *Plugin) Validate(options interface{}) error {
	return nil
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if len(params) > 0 {
		return nil, errors.New("Too many parameters.")
	}

	result, err = p.getUsersNum()
	if err != nil {
		return nil, fmt.Errorf("Failed to get logged in user count: %s.", err.Error())
	}

	return
}

func init() {
	plugin.RegisterMetrics(&impl, "Users", "system.users.num", "Returns number of useres logged in.")
}
