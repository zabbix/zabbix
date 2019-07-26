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
	"errors"
	"zabbix/pkg/log"
)

var Metrics map[string]Accessor = make(map[string]Accessor)

func RegisterMetric(impl Accessor, name string, key string, description string) {
	if _, ok := Metrics[key]; ok {
		log.Warningf("cannot register duplicate metric \"%s\"", key)
		return
	}

	impl.Init(name, key, description)
	Metrics[key] = impl
}

func Get(key string) (acc Accessor, err error) {
	var ok bool
	if acc, ok = Metrics[key]; ok {
		return
	}
	return nil, errors.New("no plugin found")
}

func ClearRegistry() {
	Metrics = make(map[string]Accessor)
}
