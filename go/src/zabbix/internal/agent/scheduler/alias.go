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

package scheduler

import (
	"fmt"
	"strings"
	"zabbix/internal/agent"
	"zabbix/pkg/itemutil"
)

type alias struct {
	name string
	key  string
}

var aliases []alias

func loadAlias(options agent.AgentOptions) (err error) {
	aliases = make([]alias, 0)
	for _, data := range options.Alias {
		var name, key string
		if name, key, err = itemutil.ParseAlias(data); err != nil {
			return fmt.Errorf("cannot add alias \"%s\": %s", data, err)
		}
		for _, existingAlias := range aliases {
			if existingAlias.name == name {
				return fmt.Errorf("failed to add Alias \"%s\": duplicate name", name)
			}
		}
		var a alias
		a.name = name
		a.key = key
		aliases = append(aliases, a)
	}

	return nil
}

func getAlias(orig string) string {
	if _, _, err := itemutil.ParseKey(orig); err != nil {
		return orig
	}
	for _, a := range aliases {
		if strings.Compare(a.name, orig) == 0 {
			return a.key
		}
	}
	for _, a := range aliases {
		aliasLn := len(a.name)
		if aliasLn <= 3 || a.name[aliasLn-3:] != `[*]` {
			continue
		}
		if aliasLn > len(orig) {
			aliasLn = len(orig)
		}
		if strings.Compare(a.name[:aliasLn-2], orig[:aliasLn-2]) != 0 {
			continue
		}
		if len(a.key) <= 3 || a.key[len(a.key)-3:] != `[*]` {
			return a.key
		}
		return string(a.key[:len(a.key)-3] + orig[len(a.name)-3:])
	}
	return orig
}
