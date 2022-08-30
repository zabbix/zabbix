//go:build amd64
// +build amd64

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

package itemutil

import (
	"reflect"
	"testing"
)

func TestParseKey(t *testing.T) {
	type Result struct {
		input  string
		failed bool
		key    string
		params []string
	}

	results := []Result{
		Result{input: `key`, key: `key`, params: []string{}},
		Result{input: `key[]`, key: `key`, params: []string{``}},
		Result{input: `key[""]`, key: `key`, params: []string{``}},
		Result{input: `key[ ]`, key: `key`, params: []string{``}},
		Result{input: `key[ ""]`, key: `key`, params: []string{``}},
		Result{input: `key[ "" ]`, key: `key`, params: []string{``}},
		Result{input: `key[a]`, key: `key`, params: []string{`a`}},
		Result{input: `key[ a]`, key: `key`, params: []string{`a`}},
		Result{input: `key[ a ]`, key: `key`, params: []string{`a `}},
		Result{input: `key["a"]`, key: `key`, params: []string{`a`}},
		Result{input: `key["a",]`, key: `key`, params: []string{`a`, ``}},
		Result{input: `key[a,]`, key: `key`, params: []string{`a`, ``}},
		Result{input: `key[a,b,c]`, key: `key`, params: []string{`a`, `b`, `c`}},
		Result{input: `key["a","b","c"]`, key: `key`, params: []string{`a`, `b`, `c`}},
		Result{input: `key[a,[b,c]]`, key: `key`, params: []string{`a`, `b,c`}},
		Result{input: `key[a,[b,]]`, key: `key`, params: []string{`a`, `b,`}},
		Result{input: `key[a,b[c]`, key: `key`, params: []string{`a`, `b[c`}},
		Result{input: `key["a","b",["c","d\",]"]]`, key: `key`, params: []string{`a`, `b`, `"c","d\",]"`}},
		Result{input: `key["a","b",["c","d\",]"],[e,f]]`, key: `key`, params: []string{`a`, `b`, `"c","d\",]"`, `e,f`}},
		Result{input: `key[a"b"]`, key: `key`, params: []string{`a"b"`}},
		Result{input: `key["a",b"c",d]`, key: `key`, params: []string{`a`, `b"c"`, `d`}},
		Result{input: `key["\"aaa\"", "bbb","ccc" , "ddd" ,"", "","" , "" ,, ,  ,eee, fff,ggg , hhh" ]`, key: `key`,
			params: []string{`"aaa"`, `bbb`, `ccc`, `ddd`, ``, ``, ``, ``, ``, ``, ``, `eee`, `fff`, `ggg `, `hhh" `}},
		Result{input: `key[["a",]`, failed: true},
		Result{input: `key[[a"]"]`, failed: true},
		Result{input: `key[["a","\"b\"]"]`, failed: true},
		Result{input: `key["a",["b","c\"]"]]]`, failed: true},
		Result{input: `key[a ]]`, failed: true},
		Result{input: `key[ a]]`, failed: true},
		Result{input: `key[ГУГЛ]654`, failed: true},
		Result{input: `{}key`, failed: true},
		Result{input: `ssh,21`, failed: true},
		Result{input: `key[][]`, failed: true},
		Result{input: `key["a",b,["c","d\",]"]]["d"]`, failed: true},
		Result{input: `key[[[]]]`, failed: true},
		Result{input: `key["a",["b",["c","d"],e],"f"]`, failed: true},
		Result{input: `key["a","b",[["c","d\",]"]]]`, failed: true},
		Result{input: `key[a]]`, failed: true},
		Result{input: `key[a[b]]`, failed: true},
		Result{input: `key["a",b[c,d],e]`, failed: true},
		Result{input: `key["a"b]`, failed: true},
		Result{input: `key["a",["b","]"c]]`, failed: true},
		Result{input: `key[["]"a]]`, failed: true},
		Result{input: `key[[a]"b"]`, failed: true},
		Result{input: `key[a,[ b , c ]]`, key: `key`, params: []string{`a`, `b ,c `}},
		Result{input: `key[a,[ " b " , " c " ]]`, key: `key`, params: []string{`a`, `" b "," c "`}},
		Result{input: `key[[a`, failed: true},
		Result{input: `key[[a,`, failed: true},
		Result{input: `key[[a `, failed: true},
		Result{input: `key[["a"`, failed: true},
		Result{input: `key[["a",`, failed: true},
		Result{input: `key[["a" `, failed: true},
		Result{input: `key["a"`, failed: true},
		Result{input: `key["a",`, failed: true},
		Result{input: `key["a" `, failed: true},
		Result{input: `key[a`, failed: true},
		Result{input: `key[a,`, failed: true},
		Result{input: `key[a `, failed: true},
		Result{input: `key[`, failed: true},
		Result{input: `key[ `, failed: true},
		Result{input: `key[,`, failed: true},
		Result{input: `key[, `, failed: true},
		Result{input: `key["\1"]`, key: `key`, params: []string{`\1`}},
		Result{input: ``, failed: true},
		Result{input: ` `, failed: true},
		Result{input: `[a]`, failed: true},
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
	type Result struct {
		key    string
		params []string
		output string
	}

	results := []*Result{
		&Result{key: "key", params: []string{}, output: `key`},
		&Result{key: "key", params: []string{`1`}, output: `key[1]`},
		&Result{key: "key", params: []string{`1`, `2`}, output: `key[1,2]`},
		&Result{key: "key", params: []string{`1,2`, `3`}, output: `key["1,2",3]`},
		&Result{key: "key", params: []string{`1,2,"3"`}, output: `key["1,2,\"3\""]`},
		&Result{key: "key", params: []string{`]`}, output: `key["]"]`},
		&Result{key: "key", params: []string{`"`}, output: `key["\""]`},
		&Result{key: "key", params: []string{` `}, output: `key[" "]`},
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
	type Result struct {
		input  string
		name   string
		key    string
		failed bool
	}

	results := []Result{
		Result{input: `a:b`, name: `a`, key: `b`},
		Result{input: `alias.name:key`, name: `alias.name`, key: `key`},
		Result{input: `alias.name:key[a]`, name: `alias.name`, key: `key[a]`},
		Result{input: `alias.name:key[a ]`, name: `alias.name`, key: `key[a ]`},
		Result{input: `alias.name:key[ a]`, name: `alias.name`, key: `key[ a]`},
		Result{input: `alias.name:key[ a ]`, name: `alias.name`, key: `key[ a ]`},
		Result{input: `alias.name[a ]:key`, name: `alias.name[a ]`, key: `key`},
		Result{input: `alias.name[ a]:key`, name: `alias.name[ a]`, key: `key`},
		Result{input: `alias.name[ a ]:key`, name: `alias.name[ a ]`, key: `key`},
		Result{input: `alias.name[]:key`, name: `alias.name[]`, key: `key`},
		Result{input: `alias.name:key[]`, name: `alias.name`, key: `key[]`},
		Result{input: `alias.name[]:key[]`, name: `alias.name[]`, key: `key[]`},
		Result{input: `alias.name[ ]:key`, name: `alias.name[ ]`, key: `key`},
		Result{input: `alias.name:key[ ]`, name: `alias.name`, key: `key[ ]`},
		Result{input: `alias.name[ ]:key[ ]`, name: `alias.name[ ]`, key: `key[ ]`},
		Result{input: `alias.name:key[*]`, name: `alias.name`, key: `key[*]`},
		Result{input: `alias.name[*]:key`, name: `alias.name[*]`, key: `key`},
		Result{input: `alias.name[*]:key[*]`, name: `alias.name[*]`, key: `key[*]`},
		Result{input: `alias.name[ *]:key[ *]`, name: `alias.name[ *]`, key: `key[ *]`},
		Result{input: `alias.name[ *]:key[a]`, name: `alias.name[ *]`, key: `key[a]`},
		Result{input: `alias.name:key s`, failed: true},
		Result{input: `a alias.name:key`, failed: true},
		Result{input: `alias.name:key[param]a`, failed: true},
		Result{input: `alias.name[:key[a]`, failed: true},
		Result{input: `alias.name[:key`, failed: true},
		Result{input: `alias.name :key`, failed: true},
		Result{input: `alias.name: key`, failed: true},
		Result{input: `alias.name : key`, failed: true},
		Result{input: `alias.name`, failed: true},
		Result{input: `alias.name:`, failed: true},
		Result{input: `:`, failed: true},
		Result{input: ``, failed: true},
		Result{input: `a\:b`, failed: true},
		Result{input: `a\\:b`, failed: true},
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
