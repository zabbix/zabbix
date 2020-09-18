/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
	"errors"
	"fmt"

	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/version"
)

// Plugin -
type Plugin struct {
	plugin.Base
}

var impl Plugin
var hostnames = map[uint64]string{}
var FirstHostname string

func SetHostname(clientID uint64, hostname string) {
	hostnames[clientID] = hostname
}

func getHostname(clientID uint64) string {
	return hostnames[clientID]
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if len(params) > 0 {
		return nil, errors.New("Too many parameters")
	}

	switch key {
	case "agent.hostname":
		if ctx.ClientID() > MaxBuiltinClientID {
			return getHostname(ctx.ClientID()), nil
		}
		return FirstHostname, nil
	case "agent.ping":
		return 1, nil
	case "agent.version":
		return version.Long(), nil
	}

	return nil, fmt.Errorf("Not implemented: %s", key)
}

func init() {
	plugin.RegisterMetrics(&impl, "Agent",
		"agent.hostname", "Returns Hostname from agent configuration.",
		"agent.ping", "Returns agent availability check result.",
		"agent.version", "Version of Zabbix agent.")
}
