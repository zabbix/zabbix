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

package plugin

import (
	"fmt"
	"zabbix/pkg/log"
)

var plugins map[Accessor]*Plugin = make(map[Accessor]*Plugin)
var metrics map[string]*Plugin = make(map[string]*Plugin)

func RegisterMetric(impl Accessor, name string, key string, description string) {
	if _, ok := metrics[key]; ok {
		log.Warningf("cannot register duplicate metric \"%s\"", key)
		return
	}
	var plugin *Plugin
	var ok bool
	if plugin, ok = plugins[impl]; !ok {
		impl.Init(name, description)
		plugin = NewPlugin(impl)
		plugins[impl] = plugin
	}
	metrics[key] = plugin
}

func Get(key string) (p *Plugin, err error) {
	if p, ok := metrics[key]; !ok {
		return nil, fmt.Errorf("cannot find plugin for metric \"%s\"", key)
	} else {
		return p, nil
	}
}
