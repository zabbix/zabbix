//go:build linux && amd64
// +build linux,amd64

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
	"reflect"
	"testing"

	"git.zabbix.com/ap/plugin-support/std"
)

func TestFileRegmatch(t *testing.T) {
	stdOs = std.NewMockOs()

	impl.options.Timeout = 3

	stdOs.(std.MockOs).MockFile("text.txt", []byte{0xd0, 0xd2, 0xd3, 0xe3, 0xe1, 0xe2, 0xd0, 0x0d, 0x0a})
	if result, err := impl.Export("vfs.file.regmatch", []string{"text.txt", "(а)", "iso-8859-5", "", ""}, nil); err != nil {
		t.Errorf("vfs.file.regmatch returned error %s", err.Error())
	} else {
		if match, ok := result.(int); !ok {
			t.Errorf("vfs.file.regmatch returned unexpected value type %s", reflect.TypeOf(result).Kind())
		} else {
			if match != 1 {
				t.Errorf("vfs.file.regmatch returned invalid result")
			}
		}
	}
}

func TestFileRegmatchUTF16(t *testing.T) {
	stdOs = std.NewMockOs()

	impl.options.Timeout = 3
	fmt.Println("AAAAAAAAAAAAAAAA")

	stdOs.(std.MockOs).MockFile("test_error_utf16le.txt", []byte{0x6200, 0x6100, 0x6400, 0x6700, 0x6500, 0x7200, 0x0a00, 0x6500, 0x7200, 0x7200, 0x6f00, 0x7200, 0x0a00, 0x6200, 0x6100, 0x6400, 0x6700, 0x6500, 0x7200, 0x3200, 0x0a00})
	if result, err := impl.Export("vfs.file.regmatch", []string{"test_error_utf16le.txt", "error", "UTF16LE", "", ""}, nil); err != nil {
		t.Errorf("vfs.file.regmatch returned error %s", err.Error())
	} else {
		if match, ok := result.(int); !ok {
			t.Errorf("vfs.file.regmatch returned unexpected value type %s", reflect.TypeOf(result).Kind())
		} else {
			if match != 1 {
				t.Errorf("vfs.file.regmatch returned invalid result")
			}
		}
	}
}
