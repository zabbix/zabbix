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
	"testing"

	"golang.zabbix.com/agent2/pkg/zbxtest"
)

func TestFileRegmatch(t *testing.T) {
	var ctx zbxtest.MockEmptyCtx
	filename := "/tmp/zbx_vfs_file_regmatch_test.dat"

	type testCase struct {
		fileContents   []byte
		targetSearch   string
		targetEncoding string
		lineStart      string
		lineEnd        string
		match          int
	}

	//августа\r\n
	fileContents_1_ISO_8859_5 := []byte{0xd0, 0xd2, 0xd3, 0xe3, 0xe1, 0xe2, 0xd0, 0x0d, 0x0a}

	// badger
	// error
	// badger2
	fileContents_1_UTF_16LE := []byte{
		0x62, 0x00, 0x61, 0x00, 0x64, 0x00, 0x67, 0x00, 0x65, 0x00, 0x72, 0x00, 0x0a, 0x00, 0x65, 0x00,
		0x72, 0x00, 0x72, 0x00, 0x6f, 0x00, 0x72, 0x00, 0x0a, 0x00, 0x62, 0x00, 0x61, 0x00, 0x64, 0x00,
		0x67, 0x00, 0x65, 0x00, 0x72, 0x00, 0x32, 0x00, 0x0a, 0x00}

	// выхухоль
	//
	// badger
	//
	// выхухоль2
	fileContents_2_ISO_8859_5 := []byte{
		0xd2, 0xeb, 0xe5, 0xe3, 0xe5, 0xde, 0xdb, 0xec, 0x0a, 0x0a, 0x62, 0x61, 0x64, 0x67, 0x65, 0x72,
		0x0a, 0x0a, 0xd2, 0xeb, 0xe5, 0xe3, 0xe5, 0xde, 0xdb, 0xec, 0x32, 0x0a}

	fileContents_2_UTF_16LE := []byte{
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
		{fileContents: fileContents_1_ISO_8859_5, targetSearch: "(а)", targetEncoding: "iso-8859-5",
			lineStart: "", lineEnd: "", match: 1},
		{fileContents: fileContents_1_UTF_16LE, targetSearch: "(error)", targetEncoding: "UTF-16LE",
			lineStart: "", lineEnd: "", match: 1},
		{fileContents: fileContents_2_ISO_8859_5, targetSearch: "хух", targetEncoding: "iso-8859-5",
			lineStart: "2", lineEnd: "", match: 1},
		{fileContents: fileContents_2_ISO_8859_5, targetSearch: "хух", targetEncoding: "iso-8859-5",
			lineStart: "1", lineEnd: "", match: 1},
		{fileContents: fileContents_2_ISO_8859_5, targetSearch: "выхухоль2\n", targetEncoding: "iso-8859-5",
			lineStart: "", lineEnd: "2", match: 0},
		{fileContents: fileContents_2_UTF_16LE, targetSearch: "хух", targetEncoding: "UTF-16LE",
			lineStart: "2", lineEnd: "", match: 1},
		{fileContents: fileContents_2_UTF_16LE, targetSearch: "хух", targetEncoding: "UTF-16LE",
			lineStart: "1", lineEnd: "", match: 1},
		{fileContents: fileContents_2_UTF_16LE, targetSearch: "выхухоль2\n", targetEncoding: "UTF-16LE",
			lineStart: "", lineEnd: "2", match: 0},
		{fileContents: fileContents_UTF_32BE, targetSearch: "хух", targetEncoding: "UTF-32BE",
			lineStart: "2", lineEnd: "", match: 1},
		{fileContents: fileContents_UTF_32BE, targetSearch: "хух", targetEncoding: "UTF-32BE",
			lineStart: "1", lineEnd: "", match: 1},
		{fileContents: fileContents_UTF_32BE, targetSearch: "выхухоль2\n", targetEncoding: "UTF-32BE",
			lineStart: "", lineEnd: "2", match: 0},

		{fileContents: []byte("127.0.0.1 localhost\n127.0.1.1 zabbix\n\n"), targetSearch: "localhost",
			targetEncoding: "", lineStart: "", lineEnd: "", match: 1},

		{fileContents: hostsFile, targetSearch: "localhost",
			targetEncoding: "UTF-16LE", lineStart: "", lineEnd: "", match: 1},

		{fileContents: hostsFile, targetSearch: "ll",
			targetEncoding: "UTF-16LE", lineStart: "", lineEnd: "", match: 0},

		{fileContents: hostsFile, targetSearch: "zabbix",
			targetEncoding: "UTF-16LE", lineStart: "", lineEnd: "", match: 1},

		{fileContents: hostsFile, targetSearch: "локалхост",
			targetEncoding: "UTF-16LE", lineStart: "", lineEnd: "", match: 0},

		{fileContents: hostsFile_RU, targetSearch: "локалхост",
			targetEncoding: "UTF-32LE", lineStart: "", lineEnd: "", match: 1},

		// wrong file encodings, but we cannot detect this and there is no expected match
		{fileContents: fileContents_1_UTF_16LE, targetSearch: "(error)", targetEncoding: "iso-8859-5",
			lineStart: "", lineEnd: "", match: 0},
		{fileContents: fileContents_UTF_32BE, targetSearch: "хух", targetEncoding: "iso-8859-5",
			lineStart: "2", lineEnd: "", match: 0},
		{fileContents: fileContents_2_UTF_16LE, targetSearch: "хух", targetEncoding: "UTF-32BE",
			lineStart: "2", lineEnd: "", match: 0},
		{fileContents: fileContents_UTF_32BE, targetSearch: "хух", targetEncoding: "UTF-16LE",
			lineStart: "1", lineEnd: "", match: 0}}
	for i, c := range tests {
		if err1 := os.WriteFile(filename, c.fileContents, 0644); err1 != nil {
			t.Errorf("failed to created file: %s", err1.Error())

			return
		}

		var result interface{}
		var err error

		if result, err = impl.Export("vfs.file.regmatch", []string{filename, c.targetSearch, c.targetEncoding,
			c.lineStart, c.lineEnd}, ctx); err != nil {
			t.Errorf("vfs.file.regmatch[%d] returned error %s", i, err.Error())

			return
		}

		var match int
		var ok bool

		if match, ok = result.(int); !ok {
			t.Errorf("vfs.file.regmatch returned unexpected value type %s",
				reflect.TypeOf(result).Kind())

			return
		}

		if match != c.match {
			t.Errorf("vfs.file.regmatch testcase[%d] returned invalid result: %d,"+
				" while expected: %d", i, match, c.match)
		}
	}

	// a
	fileSingleCharNoNewLine := []byte{0x61}

	// alphabeta
	fileManyCharsNoNewLine := []byte{
		0x61, 0x6c, 0x70, 0x68, 0x61, 0x62, 0x65, 0x74, 0x61}

	// wrong encodings in file, but we can detect this
	testsWrongEncodingsInFile := []*testCase{
		{fileContents: fileContents_1_ISO_8859_5, targetSearch: "(а)", targetEncoding: "UTF-16LE",
			lineStart: "", lineEnd: "", match: 1},
		{fileContents: fileContents_1_ISO_8859_5, targetSearch: "(а)", targetEncoding: "UTF-32BE",
			lineStart: "", lineEnd: "", match: 1},
		{fileContents: fileSingleCharNoNewLine, targetSearch: "a", targetEncoding: "UTF-16LE",
			lineStart: "", lineEnd: "", match: 1},
		{fileContents: fileManyCharsNoNewLine, targetSearch: "a", targetEncoding: "UTF-32BE",
			lineStart: "", lineEnd: "", match: 1},
		{fileContents: fileContents_1_ISO_8859_5, targetSearch: "хух", targetEncoding: "UTF-32BE",
			lineStart: "2", lineEnd: "", match: 0}}
	expectedError := "Cannot read from file. Wrong encoding detected."
	for i, c := range testsWrongEncodingsInFile {
		if err1 := os.WriteFile(filename, c.fileContents, 0644); err1 != nil {
			t.Errorf("failed to created file: %s", err1.Error())

			return
		}

		if _, err := impl.Export("vfs.file.regmatch", []string{filename, c.targetSearch, c.targetEncoding,
			c.lineStart, c.lineEnd}, ctx); err != nil {
			if err.Error() != expectedError {
				t.Errorf(`vfs.file.regmatch testcase[%d] failed with unexpected error: %s,
					expected: %s`, i, err.Error(), expectedError)
			}
		} else {
			t.Errorf("vfs.file.regmatch testcase[%d] did NOT return error", i)
		}
	}

	// wrong targets encodings
	testsWrongTargetEncodings := []*testCase{
		{fileContents: fileContents_1_ISO_8859_5, targetSearch: "(а)", targetEncoding: "BADGER",
			lineStart: "", lineEnd: "", match: 1},
		{fileContents: fileContents_1_ISO_8859_5, targetSearch: "(а)", targetEncoding: "UTF-16L",
			lineStart: "", lineEnd: "", match: 1},
		{fileContents: fileContents_1_ISO_8859_5, targetSearch: "(а)", targetEncoding: "UTF-",
			lineStart: "", lineEnd: "", match: 1},
		{fileContents: fileSingleCharNoNewLine, targetSearch: "a", targetEncoding: "UUTF-32BE",
			lineStart: "", lineEnd: "", match: 1},
		{fileContents: fileManyCharsNoNewLine, targetSearch: "a", targetEncoding: "TF-32BE",
			lineStart: "", lineEnd: "", match: 1},
		{fileContents: fileContents_1_ISO_8859_5, targetSearch: "хух", targetEncoding: "-32",
			lineStart: "2", lineEnd: "", match: 0}}
	for i, c := range testsWrongTargetEncodings {
		if err1 := os.WriteFile(filename, c.fileContents, 0644); err1 != nil {
			t.Errorf("failed to created file: %s", err1.Error())

			return
		}

		var err error
		_, err = impl.Export("vfs.file.regmatch", []string{filename, c.targetSearch, c.targetEncoding,
			c.lineStart, c.lineEnd}, ctx)

		if nil == err {
			t.Errorf("vfs.file.regmatch (testCase[%d]) did not return error: ->%s<- when wrong target "+
				"encoding:->%s<- was used", i, expectedErrorUTF8Convert, c.targetEncoding)

			return
		} else if err.Error() != expectedErrorUTF8Convert {
			t.Errorf("vfs.file.regmatch (testCase[%d]) expected error: ->%s<-,"+
				"but it instead returned: %s when wrong target encoding: ->%s<- was used", i,
				expectedErrorUTF8Convert, err.Error(), c.targetEncoding)

			return
		}
	}
}
