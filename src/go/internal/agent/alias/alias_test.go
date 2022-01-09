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

package alias

import (
	"testing"

	"zabbix.com/internal/agent"
	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/log"
)

func TestGetAlias(t *testing.T) {
	type Result struct {
		input, key string
		fail       bool
	}

	aliases := []string{
		`x:y`,
		`a[*]:k[*]`,
		`alias[*]:key[*]`,
		`alias2:key`,
		`alias3:key[*]`,
		`alias4[*]:key`,
		`xalias4[*]:xkey`,
		`alias5[*]:key[a]`,
		`alias5[ *]:key[a]`,
		`agent.hostname:agent.ping`,
	}

	results := []Result{
		Result{input: `x`, key: `y`},
		Result{input: `a[]`, key: `k[]`},
		Result{input: `a[a,b]`, key: `k[a,b]`},
		Result{input: `alias[]`, key: `key[]`},
		Result{input: `alias[a]`, key: `key[a]`},
		Result{input: `alias[,a,]`, key: `key[,a,]`},
		Result{input: `alias[ a]`, key: `key[ a]`},
		Result{input: `alias[a,b]`, key: `key[a,b]`},
		Result{input: `alias2`, key: `key`},
		Result{input: `alias3`, key: `key[*]`},
		Result{input: `alias4[a]`, key: `key`},
		Result{input: `xalias4[a]`, key: `xkey`},
		Result{input: `alias5[b]`, key: `key[a]`},
		Result{input: `alias5[b]`, key: `key[a]`},
		Result{input: `alias5[123abc]`, key: `key[a]`},
		Result{input: `alias5[ *]`, key: `key[a]`},
		Result{input: `agent.hostname`, key: `agent.ping`},
		Result{input: `no.alias`, key: `key`, fail: true},
		Result{input: `no.alias`, key: `no.alias`, fail: true},
		Result{input: `no.alias[*]`, key: `no.alias[*]`, fail: true},
		Result{input: `no.alias[*]`, key: `no.alias[a,b]`, fail: true},
	}

	_ = log.Open(log.Console, log.Debug, "", 0)
	var options agent.AgentOptions
	_ = conf.Unmarshal([]byte{}, &options)
	options.Alias = aliases

	if manager, err := NewManager(&options); err == nil {
		for _, result := range results {
			t.Run(result.input, func(t *testing.T) {
				t.Logf("result.input: %s", result.input)
				key := manager.Get(result.input)
				if !result.fail {
					if key != result.key {
						t.Errorf("Expected key '%s' while got '%s'", result.key, key)
					}
				} else if key != result.input {
					t.Errorf("Expected original key '%s' while got '%s'", result.input, key)
				}
			})
		}
	} else {
		t.Errorf("Cannot create new manager: %s", err)
	}
}
