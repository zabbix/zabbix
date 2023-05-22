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
	//	"fmt"
	"os"
	"reflect"
	"testing"
)

func TestFileRegmatch(t *testing.T) {

	impl.options.Timeout = 3

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

	tests := []*testCase{
		&testCase{fileContents: fileContents_1_ISO_8859_5, targetSearch: "(а)", targetEncoding: "iso-8859-5", lineStart: "", lineEnd: "", match: 1},
		&testCase{fileContents: fileContents_1_UTF_16LE, targetSearch: "(error)", targetEncoding: "UTF-16LE", lineStart: "", lineEnd: "", match: 1},
		&testCase{fileContents: fileContents_2_ISO_8859_5, targetSearch: "хух", targetEncoding: "iso-8859-5", lineStart: "2", lineEnd: "", match: 1},
		&testCase{fileContents: fileContents_2_ISO_8859_5, targetSearch: "хух", targetEncoding: "iso-8859-5", lineStart: "1", lineEnd: "", match: 1},
		&testCase{fileContents: fileContents_2_ISO_8859_5, targetSearch: "выхухоль2\n", targetEncoding: "iso-8859-5", lineStart: "", lineEnd: "2", match: 0},
		&testCase{fileContents: fileContents_2_UTF_16LE, targetSearch: "хух", targetEncoding: "UTF-16LE", lineStart: "2", lineEnd: "", match: 1},
		&testCase{fileContents: fileContents_2_UTF_16LE, targetSearch: "хух", targetEncoding: "UTF-16LE", lineStart: "1", lineEnd: "", match: 1},
		&testCase{fileContents: fileContents_2_UTF_16LE, targetSearch: "выхухоль2\n", targetEncoding: "UTF-16LE", lineStart: "", lineEnd: "2", match: 0},
		&testCase{fileContents: fileContents_UTF_32BE, targetSearch: "хух", targetEncoding: "UTF-32BE", lineStart: "2", lineEnd: "", match: 1},
		&testCase{fileContents: fileContents_UTF_32BE, targetSearch: "хух", targetEncoding: "UTF-32BE", lineStart: "1", lineEnd: "", match: 1},
		&testCase{fileContents: fileContents_UTF_32BE, targetSearch: "выхухоль2\n", targetEncoding: "UTF-32BE", lineStart: "", lineEnd: "2", match: 0},
	}

	for i, c := range tests {

		if err1 := os.WriteFile(filename, c.fileContents, 0644); err1 != nil {
			t.Errorf("failed to created file: %s", err1.Error())
			return
		}

		if result, err := impl.Export("vfs.file.regmatch", []string{filename, c.targetSearch, c.targetEncoding, c.lineStart, c.lineEnd}, nil); err != nil {
			t.Errorf("vfs.file.regmatch returned error %s", err.Error())
		} else {
			if match, ok := result.(int); !ok {
				t.Errorf("vfs.file.regmatch returned unexpected value type %s", reflect.TypeOf(result).Kind())
			} else {
				if match != c.match {
					t.Errorf("vfs.file.regmatch testcase[%d] returned invalid result: %d, while expected: %d", i, match, c.match)
				}
			}
		}
	}
}
