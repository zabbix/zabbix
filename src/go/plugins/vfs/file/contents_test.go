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
	"testing"
)

func TestFileContentsEncoding(t *testing.T) {
	impl.options.Timeout = 3

	filename := "/tmp/zbx_vfs_file_contents_test.dat"

	type testCase struct {
		fileContents   []byte
		targetEncoding string
		targetContents string
	}

	// августа\r\n
	fileContents_UTF_8 := []byte{
		0xfe, 0xff, 0x04, 0x30, 0x04, 0x32, 0x04, 0x33, 0x04, 0x43, 0x04, 0x41, 0x04, 0x42, 0x04, 0x30,
		0x00, 0x0d, 0x00, 0x0a}

	fileContents_2_UTF_8 := []byte{
		208, 176, 208, 178, 208, 179, 209, 131, 209, 129, 209, 130, 208, 176, 13, 10}

	// кирпич
	//
	//  еще кирпич
	// и еще один
	fileContents_ISO_8859_5 := []byte{
		0xda, 0xd8, 0xe0, 0xdf, 0xd8, 0xe7, 0x20, 0x0a, 0x0a, 0x20, 0xd5, 0xe9, 0xd5, 0x20, 0xda, 0xd8,
		0xe0, 0xdf, 0xd8, 0xe7, 0x0a, 0xd8, 0x20, 0xd5, 0xe9, 0xd5, 0x20, 0xde, 0xd4, 0xd8, 0xdd, 0x0a}

	// ロシアデスマン
	//
	// 🌭
	// кирпич
	fileContents_UTF_16BE := []byte{
		0x30, 0xed, 0x30, 0xb7, 0x30, 0xa2, 0x30, 0xc7, 0x30, 0xb9, 0x30, 0xde, 0x30, 0xf3, 0x00, 0x0a,
		0x00, 0x0a, 0xd8, 0x3c, 0xdf, 0x2d, 0x00, 0x0a, 0x04, 0x3a, 0x04, 0x38, 0x04, 0x40, 0x04, 0x3f,
		0x04, 0x38, 0x04, 0x47, 0x00, 0x0a}

	fileContents_UTF_32LE := []byte{
		0xed, 0x30, 0x00, 0x00, 0xb7, 0x30, 0x00, 0x00, 0xa2, 0x30, 0x00, 0x00, 0xc7, 0x30, 0x00, 0x00,
		0xb9, 0x30, 0x00, 0x00, 0xde, 0x30, 0x00, 0x00, 0xf3, 0x30, 0x00, 0x00, 0x0a, 0x00, 0x00, 0x00,
		0x0a, 0x00, 0x00, 0x00, 0x2d, 0xf3, 0x01, 0x00, 0x0a, 0x00, 0x00, 0x00, 0x3a, 0x04, 0x00, 0x00,
		0x38, 0x04, 0x00, 0x00, 0x40, 0x04, 0x00, 0x00, 0x3f, 0x04, 0x00, 0x00, 0x38, 0x04, 0x00, 0x00,
		0x47, 0x04, 0x00, 0x00, 0x0a, 0x00, 0x00, 0x00}

	tests := []*testCase{
		&testCase{fileContents: fileContents_UTF_8, targetEncoding: "", targetContents: "августа\r\n"},
		&testCase{fileContents: fileContents_2_UTF_8, targetEncoding: "", targetContents: "августа\r\n"},
		&testCase{fileContents: []byte{}, targetEncoding: "iso-8859-5", targetContents: ""},
		&testCase{fileContents: []byte{}, targetEncoding: "UTF-32LE", targetContents: ""},
		&testCase{fileContents: []byte{0x0a, 0x0a}, targetEncoding: "", targetContents: "\n\n"},
		&testCase{fileContents: []byte{0x0a, 0x0a}, targetEncoding: "UTF-8", targetContents: "\n\n"},
		&testCase{fileContents: []byte{0x0a, 0x00, 0x00, 0x00, 0x0a, 0x00, 0x00, 0x00}, targetEncoding: "UTF-32LE", targetContents: "\n\n"},
		&testCase{fileContents: fileContents_ISO_8859_5, targetEncoding: "iso-8859-5", targetContents: "кирпич \n\n еще кирпич\nи еще один\n"},
		&testCase{fileContents: fileContents_UTF_16BE, targetEncoding: "UTF-16BE", targetContents: "ロシアデスマン\n\n🌭\nкирпич\n"},
		&testCase{fileContents: fileContents_UTF_32LE, targetEncoding: "UTF-32LE", targetContents: "ロシアデスマン\n\n🌭\nкирпич\n"}}

	for i, c := range tests {
		if err1 := os.WriteFile(filename, c.fileContents, 0644); err1 != nil {
			t.Errorf("failed to created file: %s", err1.Error())
			return
		}

		defer os.Remove(filename)

		if result, err := impl.Export("vfs.file.contents", []string{filename, c.targetEncoding}, nil); err != nil {
			t.Errorf("vfs.file.contents (testCase[%d]) returned error %s", i, err.Error())
		} else {
			if contents, ok := result.(string); !ok {
				t.Errorf("vfs.file.contents (testCase[%d]) returned unexpected value type %s", i, reflect.TypeOf(result).Kind())
			} else {
				if contents != c.targetContents {
					t.Errorf("vfs.file.contents (testCase[%d]) returned invalid result: ->%s<-, expected: ->%s<-, %x NEXT %x", i, contents, c.targetContents, []byte(contents), []byte(c.targetContents))
				}
			}
		}
	}
}
