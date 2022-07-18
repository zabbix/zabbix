//go:build linux && amd64
// +build linux,amd64

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

package wildcard

import (
	"testing"
)

func TestWilcardMinimize(t *testing.T) {
	var input = []string{
		"pat*ern***123**",
		"**",
		"*",
		"",
	}
	var expectedResult = []string{
		"pat*ern*123*",
		"*",
		"*",
		"",
	}

	for i := range input {
		w := Minimize(input[i])
		if expectedResult[i] != w {
			t.Errorf("Result \"%s\" does not match with expectations \"%s\"", w, expectedResult[i])
		}
	}
}

func TestWildcardMatch(t *testing.T) {
	type Scenario struct {
		pattern string
		values  []string
	}

	var scenarios = []Scenario{
		{pattern: "foo", values: []string{"foo"}},
		{pattern: "*", values: []string{"abc", "123", ""}},
		{pattern: "", values: []string{""}},
		{pattern: "lo*ip*m", values: []string{"lorem_ipsum"}},
		{pattern: "a*bc", values: []string{"aaa123bc", "aXbXbcXbc"}},
		{pattern: "a*bc*d", values: []string{"aXbXbcXXd", "aXbXabcXXd"}},
		{pattern: "abc*", values: []string{"abc", "abcdef"}},
		{pattern: "*abc", values: []string{"abc", "123abc"}},
		{pattern: "*abc*", values: []string{"abc", "123abcdef"}},
		{pattern: "a***c", values: []string{"ac", "abc", "a_whatever_c"}},
		{pattern: "***c*e", values: []string{"cde", "abce", "whatever_c123e"}},
		{pattern: "a*c***", values: []string{"ac", "abc", "a1c_whatever"}},
	}

	for _, i := range scenarios {
		for _, v := range i.values {
			if !Match(v, i.pattern) {
				t.Errorf("Value \"%s\" does not match \"%s\"", v, i.pattern)
			}
		}
	}
}

func TestWildcardMatchNeagtive(t *testing.T) {
	type Scenario struct {
		pattern string
		values  []string
	}

	var scenarios = []Scenario{
		{pattern: "foo", values: []string{"fooo", "fo", "bar"}},
		{pattern: "", values: []string{"abc"}},
		{pattern: "", values: []string{"123"}},
		{pattern: "a*bc", values: []string{"aXbXbcXb"}},
		{pattern: "a*bc*d", values: []string{"aXbXbcXb"}},
	}

	for _, i := range scenarios {
		for _, v := range i.values {
			if Match(v, i.pattern) {
				t.Errorf("Value \"%s\" unexpectedly matches \"%s\"", v, i.pattern)
			}
		}
	}
}
