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

package agent

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
