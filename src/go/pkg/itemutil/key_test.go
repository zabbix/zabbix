//go:build amd64 || arm64

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

package itemutil

import (
	"reflect"
	"testing"
)

func TestParseKey(t *testing.T) {
	var results = []struct {
		input  string
		failed bool
		key    string
		params []string
	}{
		{
			input:  `key`,
			key:    `key`,
			params: []string{},
			failed: false,
		},
		{
			input:  `key[]`,
			key:    `key`,
			params: []string{``},
			failed: false,
		},
		{
			input:  `key[""]`,
			key:    `key`,
			params: []string{``},
			failed: false,
		},
		{
			input:  `key[ ]`,
			key:    `key`,
			params: []string{``},
			failed: false,
		},
		{
			input:  `key[ ""]`,
			key:    `key`,
			params: []string{``},
			failed: false,
		},
		{
			input:  `key[ "" ]`,
			key:    `key`,
			params: []string{``},
			failed: false,
		},
		{
			input:  `key[a]`,
			key:    `key`,
			params: []string{`a`},
			failed: false,
		},
		{
			input:  `key[ a]`,
			key:    `key`,
			params: []string{`a`},
			failed: false,
		},
		{
			input:  `key[ a ]`,
			key:    `key`,
			params: []string{`a `},
			failed: false,
		},
		{
			input:  `key["a"]`,
			key:    `key`,
			params: []string{`a`},
			failed: false,
		},
		{
			input:  `key["a",]`,
			key:    `key`,
			params: []string{`a`, ``},
			failed: false,
		},
		{
			input:  `key[a,]`,
			key:    `key`,
			params: []string{`a`, ``},
			failed: false,
		},
		{
			input:  `key[a,b,c]`,
			key:    `key`,
			params: []string{`a`, `b`, `c`},
			failed: false,
		},
		{
			input:  `key["a","b","c"]`,
			key:    `key`,
			params: []string{`a`, `b`, `c`},
			failed: false,
		},
		{
			input:  `key[a,[b,c]]`,
			key:    `key`,
			params: []string{`a`, `b,c`},
			failed: false,
		},
		{
			input:  `key[a,[b,]]`,
			key:    `key`,
			params: []string{`a`, `b,`},
			failed: false,
		},
		{
			input:  `key[a,b[c]`,
			key:    `key`,
			params: []string{`a`, `b[c`},
			failed: false,
		},
		{
			input:  `key["a","b",["c","d\",]"]]`,
			key:    `key`,
			params: []string{`a`, `b`, `"c","d\",]"`},
			failed: false,
		},
		{
			input:  `key["a","b",["c","d\",]"],[e,f]]`,
			key:    `key`,
			params: []string{`a`, `b`, `"c","d\",]"`, `e,f`},
			failed: false,
		},
		{
			input:  `key[a"b"]`,
			key:    `key`,
			params: []string{`a"b"`},
			failed: false,
		},
		{
			input:  `key["\"\"a\"\"",b"c",d]`,
			key:    `key`,
			params: []string{`""a""`, `b"c"`, `d`},
			failed: false,
		},
		{
			input:  `key["\"aaa\"", "bbb","ccc" , "ddd" ,"", "","" , "" ,, ,  ,eee, fff,ggg , hhh" ]`,
			key:    `key`,
			params: []string{`"aaa"`, `bbb`, `ccc`, `ddd`, ``, ``, ``, ``, ``, ``, ``, `eee`, `fff`, `ggg `, `hhh" `},
			failed: false,
		},
		{
			input:  `key[["a",]`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[[a"]"]`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[["a","\"b\"]"]`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key["a",["b","c\"]"]]]`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[a ]]`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[ a]]`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[ГУГЛ]654`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `{}key`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `ssh,21`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[][]`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key["a",b,["c","d\",]"]]["d"]`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[[[]]]`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key["a",["b",["c","d"],e],"f"]`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key["a","b",[["c","d\",]"]]]`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[a]]`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[a[b]]`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key["a",b[c,d],e]`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key["a"b]`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key["a",["b","]"c]]`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[["]"a]]`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[[a]"b"]`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[a,[ b , c ]]`,
			key:    `key`,
			params: []string{`a`, `b ,c `},
			failed: false,
		},
		{
			input:  `key[a,[ " b " , " c " ]]`,
			key:    `key`,
			params: []string{`a`, `" b "," c "`},
			failed: false,
		},
		{
			input:  `key[[a`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[[a,`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[[a `,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[["a"`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[["a",`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[["a" `,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key["a"`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key["a",`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key["a" `,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[a`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[a,`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[a `,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[ `,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[,`,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key[, `,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `key["\1"]`,
			key:    `key`,
			params: []string{`\1`},
			failed: false,
		},
		{
			input:  ``,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  ` `,
			key:    ``,
			params: nil,
			failed: true,
		},
		{
			input:  `[a]`,
			key:    ``,
			params: nil,
			failed: true,
		},
	}

	for _, result := range results {
		t.Run(result.input, func(t *testing.T) {
			key, params, err := ParseKey(result.input)
			if err == nil {
				if key != result.key {
					t.Errorf("Expected key '%s' while got '%s'", result.key, key)
				}
				if len(result.params) != len(params) {
					t.Errorf("Expected %d parameters while got %d", len(result.params), len(params))
				}
				if len(result.params) != 0 {
					if !reflect.DeepEqual(result.params, params) {
						t.Errorf("Expected parameters '%v' while got '%v'", result.params, params)
					}
				}
			} else {
				t.Logf("Error: %s", err.Error())
				if !result.failed {
					t.Errorf("Unexpected error: %s", err.Error())
				}
			}
		})
	}
}

func TestMakeKey(t *testing.T) {
	var results = []struct {
		key    string
		params []string
		output string
	}{
		{key: "key", params: []string{}, output: `key`},
		{key: "key", params: []string{`1`}, output: `key[1]`},
		{key: "key", params: []string{`1`, `2`}, output: `key[1,2]`},
		{key: "key", params: []string{`1,2`, `3`}, output: `key["1,2",3]`},
		{key: "key", params: []string{`1,2,"3"`}, output: `key["1,2,\"3\""]`},
		{key: "key", params: []string{`]`}, output: `key["]"]`},
		{key: "key", params: []string{`"`}, output: `key["\""]`},
		{key: "key", params: []string{` `}, output: `key[" "]`},
	}

	for _, r := range results {
		t.Run(r.output, func(t *testing.T) {
			text := MakeKey(r.key, r.params)
			if text != r.output {
				t.Errorf("Expected %s while got %s", r.output, text)
			}
		})
	}
}

func TestParseAlias(t *testing.T) {
	var results = []struct {
		input  string
		name   string
		key    string
		failed bool
	}{
		{input: `a:b`, name: `a`, key: `b`},
		{input: `alias.name:key`, name: `alias.name`, key: `key`},
		{input: `alias.name:key[a]`, name: `alias.name`, key: `key[a]`},
		{input: `alias.name:key[a ]`, name: `alias.name`, key: `key[a ]`},
		{input: `alias.name:key[ a]`, name: `alias.name`, key: `key[ a]`},
		{input: `alias.name:key[ a ]`, name: `alias.name`, key: `key[ a ]`},
		{input: `alias.name[a ]:key`, name: `alias.name[a ]`, key: `key`},
		{input: `alias.name[ a]:key`, name: `alias.name[ a]`, key: `key`},
		{input: `alias.name[ a ]:key`, name: `alias.name[ a ]`, key: `key`},
		{input: `alias.name[]:key`, name: `alias.name[]`, key: `key`},
		{input: `alias.name:key[]`, name: `alias.name`, key: `key[]`},
		{input: `alias.name[]:key[]`, name: `alias.name[]`, key: `key[]`},
		{input: `alias.name[ ]:key`, name: `alias.name[ ]`, key: `key`},
		{input: `alias.name:key[ ]`, name: `alias.name`, key: `key[ ]`},
		{input: `alias.name[ ]:key[ ]`, name: `alias.name[ ]`, key: `key[ ]`},
		{input: `alias.name:key[*]`, name: `alias.name`, key: `key[*]`},
		{input: `alias.name[*]:key`, name: `alias.name[*]`, key: `key`},
		{input: `alias.name[*]:key[*]`, name: `alias.name[*]`, key: `key[*]`},
		{input: `alias.name[ *]:key[ *]`, name: `alias.name[ *]`, key: `key[ *]`},
		{input: `alias.name[ *]:key[a]`, name: `alias.name[ *]`, key: `key[a]`},
		{input: `alias.name:key s`, failed: true},
		{input: `a alias.name:key`, failed: true},
		{input: `alias.name:key[param]a`, failed: true},
		{input: `alias.name[:key[a]`, failed: true},
		{input: `alias.name[:key`, failed: true},
		{input: `alias.name :key`, failed: true},
		{input: `alias.name: key`, failed: true},
		{input: `alias.name : key`, failed: true},
		{input: `alias.name`, failed: true},
		{input: `alias.name:`, failed: true},
		{input: `:`, failed: true},
		{input: ``, failed: true},
		{input: `a\:b`, failed: true},
		{input: `a\\:b`, failed: true},
	}

	for _, result := range results {
		t.Run(result.input, func(t *testing.T) {
			t.Logf("result.input: %s", result.input)
			name, key, err := ParseAlias(result.input)
			if err == nil {
				if name != result.name {
					t.Errorf("Expected alias '%s' while got '%s'", result.name, name)
				}
				if key != result.key {
					t.Errorf("Expected key '%s' while got '%s'", result.key, key)
				}
			} else {
				t.Logf("Error: %s", err.Error())
				if !result.failed {
					t.Errorf("Unexpected error: %s", err.Error())
				}
			}

		})
	}
}
