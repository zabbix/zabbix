//go:build linux && (amd64 || arm64)

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

package file

import (
	"os"
	"reflect"
	"regexp"
	"testing"

	"golang.zabbix.com/agent2/pkg/zbxregexp"
	"golang.zabbix.com/agent2/pkg/zbxtest"
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
		{input: `1`, pattern: `1`, output: ``, result: `1`, match: true},
		{input: `1`, pattern: `2`, output: ``, result: `1`, match: false},
		{input: `123 456 789"`, pattern: `([0-9]+)`, output: `\1`, result: `123`, match: true},
		{input: `value ""`, pattern: `value "([^"]*)"`, output: `\1`, result: ``, match: true},
		{input: `b:xyz"`, pattern: `b:([^ ]+)`, output: `\\1`, result: `\1`, match: true},
		{input: `a:1 b:2`, pattern: `a:([^ ]+) b:([^ ]+)`, output: `\1,\2`, result: `1,2`, match: true},
		{input: `a:\2 b:xyz`, pattern: `a:([^ ]+) b:([^ ]+)`, output: `\1,\2`, result: `\2,xyz`, match: true},
		{input: `a value: 10 in text"`, pattern: `value: ([0-9]+)`, output: `\@`, result: `value: 10`,
			match: true},
		{input: `a value: 10 in text"`, pattern: `value: ([0-9]+)`, output: `\0`, result: `value: 10`,
			match: true},
		{input: `a:9 b:2`, pattern: `a:([^\d ]+) | b:([^ ]+)`, output: `\0,\1,\2`, result: ` b:2,,2`,
			match: true},
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
	var ctx zbxtest.MockEmptyCtx
	filename := "/tmp/zbx_vfs_file_regexp_test.dat"

	type testCase struct {
		fileContents      []byte
		targetSearch      string
		targetEncoding    string
		lineStart         string
		lineEnd           string
		targetStringGroup string
		targetContents    string
	}

	// феофан\r\n
	fileContents_1_ISO_8859_5 := []byte{0xe4, 0xd5, 0xde, 0xe4, 0xd0, 0xdd, 0x0d, 0x0a}

	//августа\r\n
	fileContents_2_ISO_8859_5 := []byte{0xd0, 0xd2, 0xd3, 0xe3, 0xe1, 0xe2, 0xd0, 0x0d, 0x0a}

	// выхухоль
	//
	// badger
	//
	// выхухоль2
	fileContents_3_ISO_8859_5 := []byte{
		0xd2, 0xeb, 0xe5, 0xe3, 0xe5, 0xde, 0xdb, 0xec, 0x0a, 0x0a, 0x62, 0x61, 0x64, 0x67, 0x65, 0x72,
		0x0a, 0x0a, 0xd2, 0xeb, 0xe5, 0xe3, 0xe5, 0xde, 0xdb, 0xec, 0x32, 0x0a}
	fileContents_UTF_16LE := []byte{
		0x32, 0x04, 0x4b, 0x04, 0x45, 0x04, 0x43, 0x04, 0x45, 0x04, 0x3e, 0x04, 0x3b, 0x04, 0x4c, 0x04,
		0x0a, 0x00, 0x0a, 0x00, 0x62, 0x00, 0x61, 0x00, 0x64, 0x00, 0x67, 0x00, 0x65, 0x00, 0x72, 0x00,
		0x0a, 0x00, 0x0a, 0x00, 0x32, 0x04, 0x4b, 0x04, 0x45, 0x04, 0x43, 0x04, 0x45, 0x04, 0x3e, 0x04,
		0x3b, 0x04, 0x4c, 0x04, 0x32, 0x00, 0x0a, 0x00}
	fileContents_UTF_32BE := []byte{
		0x00, 0x00, 0x04, 0x32, 0x00, 0x00, 0x04, 0x4b, 0x00, 0x00, 0x04, 0x45, 0x00, 0x00, 0x04, 0x43,
		0x00, 0x00, 0x04, 0x45, 0x00, 0x00, 0x04, 0x3e, 0x00, 0x00, 0x04, 0x3b, 0x00, 0x00, 0x04, 0x4c,
		0x00, 0x00, 0x00, 0x0a, 0x00, 0x00, 0x00, 0x0a, 0x00, 0x00, 0x00, 0x62, 0x00, 0x00, 0x00, 0x61,
		0x00, 0x00, 0x00, 0x64, 0x00, 0x00, 0x00, 0x67, 0x00, 0x00, 0x00, 0x65, 0x00, 0x00, 0x00, 0x72,
		0x00, 0x00, 0x00, 0x0a, 0x00, 0x00, 0x00, 0x0a, 0x00, 0x00, 0x04, 0x32, 0x00, 0x00, 0x04, 0x4b,
		0x00, 0x00, 0x04, 0x45, 0x00, 0x00, 0x04, 0x43, 0x00, 0x00, 0x04, 0x45, 0x00, 0x00, 0x04, 0x3e,
		0x00, 0x00, 0x04, 0x3b, 0x00, 0x00, 0x04, 0x4c, 0x00, 0x00, 0x00, 0x32, 0x00, 0x00, 0x00, 0x0a}

	// 127.0.0.1 localhost
	// 127.0.1.1 zabbix
	//
	hostsFile := []byte{
		0x31, 0x00, 0x32, 0x00, 0x37, 0x00, 0x2e, 0x00, 0x30, 0x00, 0x2e, 0x00, 0x30, 0x00, 0x2e, 0x00,
		0x31, 0x00, 0x20, 0x00, 0x6c, 0x00, 0x6f, 0x00, 0x63, 0x00, 0x61, 0x00, 0x6c, 0x00, 0x68, 0x00,
		0x6f, 0x00, 0x73, 0x00, 0x74, 0x00, 0x0a, 0x00, 0x31, 0x00, 0x32, 0x00, 0x37, 0x00, 0x2e, 0x00,
		0x30, 0x00, 0x2e, 0x00, 0x31, 0x00, 0x2e, 0x00, 0x31, 0x00, 0x20, 0x00, 0x7a, 0x00, 0x61, 0x00,
		0x62, 0x00, 0x62, 0x00, 0x69, 0x00, 0x78, 0x00, 0x0a, 0x00, 0x0a, 0x00}

	// 127.0.0.1 локалхост
	// 127.0.1.1 заббикс
	//
	hostsFile_RU := []byte{
		0x31, 0x00, 0x00, 0x00, 0x32, 0x00, 0x00, 0x00, 0x37, 0x00, 0x00, 0x00, 0x2e, 0x00, 0x00, 0x00,
		0x30, 0x00, 0x00, 0x00, 0x2e, 0x00, 0x00, 0x00, 0x30, 0x00, 0x00, 0x00, 0x2e, 0x00, 0x00, 0x00,
		0x31, 0x00, 0x00, 0x00, 0x20, 0x00, 0x00, 0x00, 0x3b, 0x04, 0x00, 0x00, 0x3e, 0x04, 0x00, 0x00,
		0x3a, 0x04, 0x00, 0x00, 0x30, 0x04, 0x00, 0x00, 0x3b, 0x04, 0x00, 0x00, 0x45, 0x04, 0x00, 0x00,
		0x3e, 0x04, 0x00, 0x00, 0x41, 0x04, 0x00, 0x00, 0x42, 0x04, 0x00, 0x00, 0x0a, 0x00, 0x00, 0x00,
		0x31, 0x00, 0x00, 0x00, 0x32, 0x00, 0x00, 0x00, 0x37, 0x00, 0x00, 0x00, 0x2e, 0x00, 0x00, 0x00,
		0x30, 0x00, 0x00, 0x00, 0x2e, 0x00, 0x00, 0x00, 0x31, 0x00, 0x00, 0x00, 0x2e, 0x00, 0x00, 0x00,
		0x31, 0x00, 0x00, 0x00, 0x20, 0x00, 0x00, 0x00, 0x37, 0x04, 0x00, 0x00, 0x30, 0x04, 0x00, 0x00,
		0x31, 0x04, 0x00, 0x00, 0x31, 0x04, 0x00, 0x00, 0x38, 0x04, 0x00, 0x00, 0x3a, 0x04, 0x00, 0x00,
		0x41, 0x04, 0x00, 0x00, 0x0a, 0x00, 0x00, 0x00, 0x0a, 0x00, 0x00, 0x00}

	tests := []*testCase{
		{fileContents: fileContents_1_ISO_8859_5, targetSearch: "(ф)", targetEncoding: "iso-8859-5",
			lineStart: "", lineEnd: "", targetStringGroup: "group 0: \\0 group 1: \\1 group 4: \\4",
			targetContents: "group 0: ф group 1: ф group 4: "},

		{fileContents: fileContents_2_ISO_8859_5, targetSearch: "(а)", targetEncoding: "iso-8859-5",
			lineStart: "", lineEnd: "", targetStringGroup: "", targetContents: "августа"},

		// выхухоль
		//
		// badger
		//
		// выхухоль2
		{fileContents: fileContents_3_ISO_8859_5, targetSearch: "хух", targetEncoding: "iso-8859-5",
			lineStart: "2", lineEnd: "", targetStringGroup: "", targetContents: "выхухоль2"},

		{fileContents: fileContents_3_ISO_8859_5, targetSearch: "хух", targetEncoding: "iso-8859-5",
			lineStart: "1", lineEnd: "", targetStringGroup: "", targetContents: "выхухоль"},

		{fileContents: fileContents_3_ISO_8859_5, targetSearch: "выхухоль2\n", targetEncoding: "iso-8859-5",
			lineStart: "", lineEnd: "2", targetStringGroup: "", targetContents: ""},

		{fileContents: fileContents_UTF_16LE, targetSearch: "хух", targetEncoding: "UTF-16LE", lineStart: "2",
			lineEnd: "", targetStringGroup: "", targetContents: "выхухоль2"},

		{fileContents: fileContents_UTF_16LE, targetSearch: "хух", targetEncoding: "UTF-16LE", lineStart: "1",
			lineEnd: "", targetStringGroup: "", targetContents: "выхухоль"},

		{fileContents: fileContents_UTF_16LE, targetSearch: "выхухоль2\n", targetEncoding: "UTF-16LE",
			lineStart: "", lineEnd: "2", targetStringGroup: "", targetContents: ""},

		{fileContents: fileContents_UTF_32BE, targetSearch: "хух", targetEncoding: "UTF-32BE", lineStart: "2",
			lineEnd: "", targetStringGroup: "", targetContents: "выхухоль2"},

		{fileContents: fileContents_UTF_32BE, targetSearch: "хух", targetEncoding: "UTF-32BE", lineStart: "1",
			lineEnd: "", targetStringGroup: "", targetContents: "выхухоль"},

		{fileContents: fileContents_UTF_32BE, targetSearch: "выхухоль2\n", targetEncoding: "UTF-32BE",
			lineStart: "", lineEnd: "2", targetStringGroup: "", targetContents: ""},

		{fileContents: []byte("127.0.0.1 localhost\n127.0.1.1 zabbix\n\n"), targetSearch: "localhost",
			targetEncoding: "", lineStart: "", lineEnd: "", targetStringGroup: "",
			targetContents: "127.0.0.1 localhost"},

		{fileContents: hostsFile, targetSearch: "localhost",
			targetEncoding: "UTF-16LE", lineStart: "", lineEnd: "", targetStringGroup: "",
			targetContents: "127.0.0.1 localhost"},

		{fileContents: hostsFile, targetSearch: "ll",
			targetEncoding: "UTF-16LE", lineStart: "", lineEnd: "", targetStringGroup: "",
			targetContents: ""},

		{fileContents: hostsFile, targetSearch: "zabbix",
			targetEncoding: "UTF-16LE", lineStart: "", lineEnd: "", targetStringGroup: "",
			targetContents: "127.0.1.1 zabbix"},

		{fileContents: hostsFile, targetSearch: "локалхост",
			targetEncoding: "UTF-16LE", lineStart: "", lineEnd: "", targetStringGroup: "",
			targetContents: ""},

		{fileContents: hostsFile_RU, targetSearch: "локалхост",
			targetEncoding: "UTF-32LE", lineStart: "", lineEnd: "", targetStringGroup: "",
			targetContents: "127.0.0.1 локалхост"},

		// wrong encodings in file, but we cannot detect this and there is no expected target contents
		{fileContents: fileContents_UTF_16LE, targetSearch: "хух", targetEncoding: "iso-8859-5", lineStart: "2",
			lineEnd: "", targetStringGroup: "", targetContents: ""},
		{fileContents: fileContents_UTF_32BE, targetSearch: "хух", targetEncoding: "iso-8859-5", lineStart: "2",
			lineEnd: "", targetStringGroup: "", targetContents: ""},

		{fileContents: fileContents_3_ISO_8859_5, targetSearch: "хух", targetEncoding: "UTF-16LE",
			lineStart: "2", lineEnd: "", targetStringGroup: "", targetContents: ""},

		{fileContents: fileContents_3_ISO_8859_5, targetSearch: "хух", targetEncoding: "UTF-32BE",
			lineStart: "1", lineEnd: "", targetStringGroup: "", targetContents: ""},

		{fileContents: fileContents_UTF_16LE, targetSearch: "хух", targetEncoding: "UTF-32BE",
			lineStart: "1", lineEnd: "", targetStringGroup: "", targetContents: ""},

		{fileContents: fileContents_UTF_32BE, targetSearch: "хух", targetEncoding: "UTF-16LE",
			lineStart: "1", lineEnd: "", targetStringGroup: "", targetContents: ""}}

	for i, c := range tests {
		if err1 := os.WriteFile(filename, c.fileContents, 0644); err1 != nil {
			t.Errorf("failed to created file: %s", err1.Error())

			return
		}

		defer os.Remove(filename)

		var result interface{}
		var err error

		if result, err = impl.Export("vfs.file.regexp", []string{filename, c.targetSearch, c.targetEncoding,
			c.lineStart, c.lineEnd, c.targetStringGroup}, ctx); err != nil {
			t.Errorf("vfs.file.regexp (testCase[%d]) returned error %s", i, err.Error())

			return
		}

		var contents string
		var ok bool
		if contents, ok = result.(string); !ok {
			t.Errorf("vfs.file.regexp (testCase[%d]) returned unexpected value type %s", i,
				reflect.TypeOf(result).Kind())

			return
		}
		if contents != c.targetContents {
			t.Errorf("vfs.file.regexp (testCase[%d]) returned invalid result: ->%s<-, expected: ->%s<-", i,
				contents, c.targetContents)

			return
		}
	}

	// a
	fileSingleCharNoNewLine := []byte{0x61}

	// alphabeta
	fileManyCharsNoNewLine := []byte{
		0x61, 0x6c, 0x70, 0x68, 0x61, 0x62, 0x65, 0x74, 0x61}

	// wrong encodings in file, but we can detect this
	testsWrongEncodings := []*testCase{
		{fileContents: fileSingleCharNoNewLine, targetSearch: "a", targetEncoding: "UTF-32BE",
			lineStart: "1", lineEnd: "", targetStringGroup: "", targetContents: "a"},

		{fileContents: fileManyCharsNoNewLine, targetSearch: "alpha", targetEncoding: "UTF-16LE",
			lineStart: "1", lineEnd: "", targetStringGroup: "", targetContents: "alpha"},
	}

	expectedError := "Cannot read from file. Wrong encoding detected."

	for i, c := range testsWrongEncodings {
		if err1 := os.WriteFile(filename, c.fileContents, 0644); err1 != nil {
			t.Errorf("failed to created file: %s", err1.Error())

			return
		}

		defer os.Remove(filename)

		if result, err := impl.Export("vfs.file.regexp", []string{filename, c.targetSearch, c.targetEncoding,
			c.lineStart, c.lineEnd, c.targetStringGroup}, ctx); err != nil {
			if err.Error() != expectedError {
				t.Errorf("vfs.file.regexp testcase[%d] failed with unexpected error: %s, expected: %s",
					i, err.Error(), expectedError)
			}
		} else {
			t.Errorf("vfs.file.regexp testcase[%d] did NOT return error, result: %s", i, result)
		}
	}

	// wrong targets encodings
	testsWrongTargetEncodings := []*testCase{
		{fileContents: fileContents_3_ISO_8859_5, targetSearch: "хух", targetEncoding: "BADGER",
			lineStart: "2", lineEnd: "", targetStringGroup: "", targetContents: "выхухоль2"},

		{fileContents: fileContents_3_ISO_8859_5, targetSearch: "хух", targetEncoding: "iso-8859-66",
			lineStart: "2", lineEnd: "", targetStringGroup: "", targetContents: "выхухоль2"},

		{fileContents: fileContents_3_ISO_8859_5, targetSearch: "хух", targetEncoding: "so-8859-5",
			lineStart: "1", lineEnd: "", targetStringGroup: "", targetContents: "выхухоль"},

		{fileContents: fileContents_3_ISO_8859_5, targetSearch: "выхухоль2\n", targetEncoding: "-8859-5",
			lineStart: "", lineEnd: "2", targetStringGroup: "", targetContents: ""},

		{fileContents: fileContents_UTF_16LE, targetSearch: "хух", targetEncoding: "6LE", lineStart: "2",
			lineEnd: "", targetStringGroup: "", targetContents: "выхухоль2"},

		{fileContents: fileContents_UTF_16LE, targetSearch: "хух", targetEncoding: "E", lineStart: "1",
			lineEnd: "", targetStringGroup: "", targetContents: "выхухоль"},

		{fileContents: fileContents_UTF_16LE, targetSearch: "выхухоль2\n", targetEncoding: "-",
			lineStart: "", lineEnd: "2", targetStringGroup: "", targetContents: ""},

		{fileContents: fileContents_UTF_32BE, targetSearch: "хух", targetEncoding: "UTF-32BEUTF-32BE",
			lineStart: "2", lineEnd: "", targetStringGroup: "", targetContents: "выхухоль2"},

		{fileContents: fileContents_UTF_32BE, targetSearch: "хух", targetEncoding: "-UTF-32BE", lineStart: "1",
			lineEnd: "", targetStringGroup: "", targetContents: "выхухоль"}}

	for i, c := range testsWrongTargetEncodings {
		if err1 := os.WriteFile(filename, c.fileContents, 0644); err1 != nil {
			t.Errorf("failed to created file: %s", err1.Error())

			return
		}

		var err error
		_, err = impl.Export("vfs.file.regexp", []string{filename, c.targetSearch, c.targetEncoding,
			c.lineStart, c.lineEnd, c.targetStringGroup}, ctx)

		if nil == err {
			t.Errorf("vfs.file.regexp (testCase[%d]) did not return error: ->%s<- when wrong target "+
				"encoding:->%s<- was used", i, expectedErrorUTF8Convert, c.targetEncoding)

			return
		} else if err.Error() != expectedErrorUTF8Convert {
			t.Errorf("vfs.file.regexp (testCase[%d]) expected error: ->%s<-,"+
				"but it instead returned: %s when wrong target encoding: ->%s<- was used", i,
				expectedErrorUTF8Convert, err.Error(), c.targetEncoding)

			return
		}
	}
}
