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
	"reflect"
	"testing"

	"golang.zabbix.com/sdk/std"
)

type testCase struct {
	fileContents   []byte
	targetEncoding string
	targetContents string
}

const filename = "/tmp/zbx_vfs_file_contents_test.dat"
const expectedErrorUTF8Convert = "Failed to convert from encoding to utf8: invalid argument"

// августа\r\n
var fileContents_UTF_8 = []byte{
	0xfe, 0xff, 0x04, 0x30, 0x04, 0x32, 0x04, 0x33, 0x04, 0x43, 0x04, 0x41, 0x04, 0x42, 0x04, 0x30,
	0x00, 0x0d, 0x00, 0x0a}

func TestFileContentsEncoding(t *testing.T) {

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

	// a
	fileSingleCharNoNewLine := []byte{0x61}

	// alphabeta
	fileManyCharsNoNewLine := []byte{
		0x61, 0x6c, 0x70, 0x68, 0x61, 0x62, 0x65, 0x74, 0x61}

	tests := []*testCase{
		{fileContents: fileContents_UTF_8, targetEncoding: "", targetContents: "августа"},
		{fileContents: fileContents_2_UTF_8, targetEncoding: "", targetContents: "августа"},
		{fileContents: []byte{}, targetEncoding: "iso-8859-5", targetContents: ""},
		{fileContents: []byte{}, targetEncoding: "UTF-32LE", targetContents: ""},
		{fileContents: []byte{0x0a, 0x0a}, targetEncoding: "", targetContents: ""},
		{fileContents: []byte{0x0a, 0x0a}, targetEncoding: "UTF-8", targetContents: ""},
		{fileContents: []byte{0x0a, 0x00, 0x00, 0x00, 0x0a, 0x00, 0x00, 0x00},
			targetEncoding: "UTF-32LE", targetContents: ""},
		{fileContents: fileContents_ISO_8859_5, targetEncoding: "iso-8859-5",
			targetContents: "кирпич \n\n еще кирпич\nи еще один"},
		{fileContents: fileContents_UTF_16BE, targetEncoding: "UTF-16BE",
			targetContents: "ロシアデスマン\n\n🌭\nкирпич"},
		{fileContents: fileContents_UTF_32LE, targetEncoding: "UTF-32LE",
			targetContents: "ロシアデスマン\n\n🌭\nкирпич"},
		{fileContents: fileSingleCharNoNewLine, targetEncoding: "", targetContents: "a"},
		{fileContents: fileManyCharsNoNewLine, targetEncoding: "", targetContents: "alphabeta"},
		// file contents with wrong encodings
		{fileContents: fileContents_UTF_8, targetEncoding: "UTF-16BE", targetContents: "августа"},
		{fileContents: fileContents_UTF_8, targetEncoding: "UTF-16BE", targetContents: "августа"},
		{fileContents: fileContents_ISO_8859_5, targetEncoding: "UTF-32LE", targetContents: ""},
		{fileContents: fileContents_ISO_8859_5, targetEncoding: "UTF-32LE", targetContents: ""},
		{fileContents: fileSingleCharNoNewLine, targetEncoding: "UTF-16BE", targetContents: ""},
		{fileContents: fileManyCharsNoNewLine, targetEncoding: "UTF-16BE", targetContents: "慬灨慢整"},
		{fileContents: fileSingleCharNoNewLine, targetEncoding: "UTF-32LE", targetContents: ""},
		{fileContents: fileManyCharsNoNewLine, targetEncoding: "UTF-32LE", targetContents: ""},

		// target encoding wrong, (iconv fails to detect this)
		{fileContents: fileManyCharsNoNewLine, targetEncoding: "ロシアデスマン", targetContents: "alphabeta"},
		{fileContents: fileManyCharsNoNewLine, targetEncoding: "🌭", targetContents: "alphabeta"},
		{fileContents: fileManyCharsNoNewLine, targetEncoding: "кирпич", targetContents: "alphabeta"},
		{fileContents: fileManyCharsNoNewLine, targetEncoding: "ロシавгуста\r\n", targetContents: "alphabeta"},
	}

	for i, c := range tests {
		stdOs.(std.MockOs).MockFile(filename, c.fileContents)

		var result interface{}
		var err error

		if result, err = impl.Export("vfs.file.contents", []string{filename, c.targetEncoding}, nil); err != nil {
			t.Errorf("vfs.file.contents (testCase[%d]) returned error %s", i, err.Error())

			return
		}

		var contents string
		var ok bool

		if contents, ok = result.(string); !ok {
			t.Errorf("vfs.file.contents (testCase[%d]) returned unexpected value type %s", i,
				reflect.TypeOf(result).Kind())

			return
		}

		if contents != c.targetContents {
			t.Errorf(`vfs.file.contents (testCase[%d]) returned invalid result: ->%s<-,
				expected: ->%s<-, (bytes: %x and %x)`, i, contents, c.targetContents,
				[]byte(contents), []byte(c.targetContents))
		}
	}
}

func TestFileContentsWrongTargetEncoding(t *testing.T) {
	testCasesWrongTargetEncoding := []*testCase{
		{fileContents: fileContents_UTF_8, targetEncoding: "BADGER", targetContents: ""},
		{fileContents: fileContents_UTF_8, targetEncoding: "a", targetContents: ""},
		{fileContents: fileContents_UTF_8, targetEncoding: "UTF-17", targetContents: ""},
		{fileContents: fileContents_UTF_8, targetEncoding: "UTF-", targetContents: ""},
		{fileContents: fileContents_UTF_8, targetEncoding: "UTF", targetContents: ""},
		{fileContents: fileContents_UTF_8, targetEncoding: "U", targetContents: ""},
		{fileContents: fileContents_UTF_8, targetEncoding: "_UTF-16", targetContents: ""},
	}

	for i, c := range testCasesWrongTargetEncoding {
		stdOs.(std.MockOs).MockFile(filename, c.fileContents)

		var err error
		_, err = impl.Export("vfs.file.contents", []string{filename, c.targetEncoding}, nil)

		if nil == err {
			t.Errorf("vfs.file.contents (testCase[%d]) did not return error: ->%s<- when wrong target "+
				"encoding:->%s<- was used", i, expectedErrorUTF8Convert, c.targetEncoding)

			return
		} else if err.Error() != expectedErrorUTF8Convert {
			t.Errorf("vfs.file.contents (testCase[%d]) expected error: ->%s<-,"+
				"but it instead returned: %s when wrong target encoding: ->%s<- was used", i,
				expectedErrorUTF8Convert, err.Error(), c.targetEncoding)

			return
		}
	}
}
