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

package alias

import (
	"fmt"
	"strings"

	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/pkg/itemutil"
)

type keyAlias struct {
	name, key string
}

type Manager struct {
	aliases []keyAlias
}

func (m *Manager) addAliases(aliases []string) (err error) {
	for _, data := range aliases {
		var name, key string
		if name, key, err = itemutil.ParseAlias(data); err != nil {
			return fmt.Errorf("cannot add alias \"%s\": %s", data, err)
		}
		for _, existingAlias := range m.aliases {
			if existingAlias.name == name {
				return fmt.Errorf("failed to add Alias \"%s\": duplicate name", name)
			}
		}
		m.aliases = append(m.aliases, keyAlias{name: name, key: key})
	}
	return nil
}

func NewManager(options *agent.AgentOptions) (m *Manager, err error) {
	m = &Manager{
		aliases: make([]keyAlias, 0),
	}
	if options != nil {
		if err = m.initialize(options); err != nil {
			return nil, err
		}
	}
	return
}

func (m *Manager) Get(orig string) string {
	if _, _, err := itemutil.ParseKey(orig); err != nil {
		return orig
	}
	for _, a := range m.aliases {
		if strings.Compare(a.name, orig) == 0 {
			return a.key
		}
	}
	for _, a := range m.aliases {
		aliasLn := len(a.name)
		if aliasLn <= 3 || a.name[aliasLn-3:] != `[*]` {
			continue
		}
		if aliasLn-2 > len(orig) {
			return orig
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
