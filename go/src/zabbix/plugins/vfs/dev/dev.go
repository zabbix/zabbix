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

package vfsdev

import (
	"errors"
	"zabbix/internal/plugin"
)

// Plugin -
type Plugin struct {
	plugin.Base
}

var impl Plugin

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "vfs.dev.read", "vfs.dev.write":
		return nil, errors.New("Not implemented")
	case "vfs.dev.discovery":
		return p.getDiscovery()
	default:
		return nil, errors.New("Unsupported metric")
	}

}

func init() {
	plugin.RegisterMetric(&impl, "vfsdev", "vfs.dev.read", "Disk read statistics.")
	plugin.RegisterMetric(&impl, "vfsdev", "vfs.dev.write", "Disk write statistics.")
	plugin.RegisterMetric(&impl, "vfsdev", "vfs.dev.discovery", "List of block devices and their type."+
		" Used for low-level discovery.")
}
