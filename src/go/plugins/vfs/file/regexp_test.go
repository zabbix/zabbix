/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

package file

import (
	"os"
	"reflect"
	"regexp"
	"testing"
	"zabbix.com/pkg/zbxregexp"
)

func TestExecuteRegex(t *testing.T) {
	type testCase struct {
		input   string
		pattern string
		output  string
		result  string
		match   bool
	}

	tests := []*testCase{
		&testCase{input: `1`, pattern: `1`, output: ``, result: `1`, match: true},
		&testCase{input: `1`, pattern: `2`, output: ``, result: `1`, match: false},
		&testCase{input: `123 456 789"`, pattern: `([0-9]+)`, output: `\1`, result: `123`, match: true},
		&testCase{input: `value ""`, pattern: `value "([^"]*)"`, output: `\1`, result: ``, match: true},
		&testCase{input: `b:xyz"`, pattern: `b:([^ ]+)`, output: `\\1`, result: `\1`, match: true},
		&testCase{input: `a:1 b:2`, pattern: `a:([^ ]+) b:([^ ]+)`, output: `\1,\2`, result: `1,2`, match: true},
		&testCase{input: `a:\2 b:xyz`, pattern: `a:([^ ]+) b:([^ ]+)`, output: `\1,\2`, result: `\2,xyz`, match: true},
		&testCase{input: `a value: 10 in text"`, pattern: `value: ([0-9]+)`, output: `\@`, result: `value: 10`, match: true},
		&testCase{input: `a value: 10 in text"`, pattern: `value: ([0-9]+)`, output: `\0`, result: `value: 10`, match: true},
		&testCase{input: `a:9 b:2`, pattern: `a:([^\d ]+) | b:([^ ]+)`, output: `\0,\1,\2`, result: ` b:2,,2`, match: true},
	}

	for _, c := range tests {
		t.Run(c.input, func(t *testing.T) {
			rx, _ := regexp.Compile(c.pattern)
			r, m := zbxregexp.ExecuteRegex([]byte(c.input), rx, []byte(c.output))
			if !m && c.match {
				t.Errorf("expected match while returned false")
			}
			if m && !c.match {
				t.Errorf("expected not match while returned true")
			}
			if m && r != c.result {
				t.Errorf("expected match output '%s' while got '%s'", c.result, r)
			}
		})
	}
}

func TestFileRegexpOutput(t *testing.T) {

	impl.options.Timeout = 3

	type testCase struct {
		fileContents      []byte
		targetSearch      string
		targetEncoding    string
		lineStart         string
		lineEnd           string
		targetStringGroup string
		targetContents    string
	}

	filename := "/tmp/zbx_regexp_test.dat"
	tests := []*testCase{
		&testCase{fileContents: []byte{0xe4, 0xd5, 0xde, 0xe4, 0xd0, 0xdd, 0x0d, 0x0a}, targetSearch: "(ф)", targetEncoding: "iso-8859-5", lineStart: "", lineEnd: "", targetStringGroup: "group 0: \\0 group 1: \\1 group 4: \\4", targetContents: "group 0: ф group 1: ф group 4: "},

		// августа\r\n
		&testCase{fileContents: []byte{0xd0, 0xd2, 0xd3, 0xe3, 0xe1, 0xe2, 0xd0, 0x0d, 0x0a}, targetSearch: "(а)", targetEncoding: "iso-8859-5", lineStart: "", lineEnd: "", targetStringGroup: "", targetContents: "августа\r\n"},

		// выхухоль
		//
		// badger
		//
		// выхухоль2
		&testCase{fileContents: []byte{0xd2, 0xeb, 0xe5, 0xe3, 0xe5, 0xde, 0xdb, 0xec, 0x0a, 0x0a,
			0x62, 0x61, 0x64, 0x67, 0x65, 0x72, 0x0a, 0x0a, 0xd2, 0xeb,
			0xe5, 0xe3, 0xe5, 0xde, 0xdb, 0xec, 0x32, 0x0a},
			targetSearch: "хух", targetEncoding: "iso-8859-5", lineStart: "2", lineEnd: "", targetStringGroup: "", targetContents: "выхухоль2\n"},

		&testCase{fileContents: []byte{0xd2, 0xeb, 0xe5, 0xe3, 0xe5, 0xde, 0xdb, 0xec, 0x0a, 0x0a,
			0x62, 0x61, 0x64, 0x67, 0x65, 0x72, 0x0a, 0x0a, 0xd2, 0xeb,
			0xe5, 0xe3, 0xe5, 0xde, 0xdb, 0xec, 0x32, 0x0a}, targetSearch: "хух", targetEncoding: "iso-8859-5", lineStart: "1", lineEnd: "", targetStringGroup: "", targetContents: "выхухоль\n"},

		&testCase{fileContents: []byte{0xd2, 0xeb, 0xe5, 0xe3, 0xe5, 0xde, 0xdb, 0xec, 0x0a, 0x0a,
			0x62, 0x61, 0x64, 0x67, 0x65, 0x72, 0x0a, 0x0a, 0xd2, 0xeb,
			0xe5, 0xe3, 0xe5, 0xde, 0xdb, 0xec, 0x32, 0x0}, targetSearch: "выхухоль2\n", targetEncoding: "iso-8859-5", lineStart: "", lineEnd: "2", targetStringGroup: "", targetContents: ""},

		//&testCase{fileContents: []byte{}, targetSearch: "", targetEncoding: "iso-8859-5", lineStart: "", lineEnd: "", targetStringGroup: "", targetContents: ""},
		//&testCase{fileContents: []byte{}, targetSearch: "", targetEncoding: "iso-8859-5", lineStart: "", lineEnd: "", targetStringGroup: "", targetContents: ""},

	}

	for _, c := range tests {

		if err1 := os.WriteFile(filename, c.fileContents, 0644); err1 != nil {
			t.Errorf("failed to created file: %s", err1.Error())
			return
		}

		defer os.Remove(filename)

		if result, err := impl.Export("vfs.file.regexp", []string{filename, c.targetSearch, c.targetEncoding, c.lineStart, c.lineEnd, c.targetStringGroup}, nil); err != nil {
			t.Errorf("vfs.file.regexp returned error %s", err.Error())
		} else {
			if contents, ok := result.(string); !ok {
				t.Errorf("vfs.file.regexp returned unexpected value type %s", reflect.TypeOf(result).Kind())
			} else {
				if contents != c.targetContents {
					t.Errorf("vfs.file.regexp returned invalid result: ->%s<-", contents)
				}
			}
		}
	}
}
