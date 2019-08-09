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

package agent

import (
	"fmt"
	"strings"
	"zabbix/internal/plugin"
	"zabbix/pkg/itemutil"
	"zabbix/pkg/log"
)

// Plugin -
type UserParameterPlugin struct {
	plugin.Base
}

var userparameter UserParameterPlugin

// Export -
func (p *UserParameterPlugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {

	return key, nil
}

func InitUserParameterPlugin() {
	for i := 0; i < len(Options.UserParameter); i++ {
		keyCommand := strings.SplitN(Options.UserParameter[i], ",", 2)

		if len(keyCommand) != 2 {
			log.Critf("cannot add user parameter \"%s\": not comma-separated", Options.UserParameter[i])
		}

		k, _, err := itemutil.ParseKey(keyCommand[0])
		if err != nil {
			log.Critf("cannot add user parameter \"%s\": %s", Options.UserParameter[i], err)
		}

		plugin.RegisterMetric(&userparameter, "userparameter", k, "test")
	}
	fmt.Println("test", Options.UserParameter)

}
