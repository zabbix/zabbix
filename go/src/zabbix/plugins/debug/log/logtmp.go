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

// This is placeholder for debug.log plugin based on Watcher
package log

import (
	"zabbix/internal/plugin"
	"zabbix/pkg/std"
)

// Plugin -
type Plugin struct {
	plugin.Base
}

var impl Plugin
var stdOs std.Os

func (p *Plugin) Export(key string, params []string) (result interface{}, err error) {
	return nil, nil
}

func init() {
	stdOs = std.NewOs()
	plugin.RegisterMetric(&impl, "debug.log", "debug.log", "Returns empty value")
}
