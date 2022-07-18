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

package file

import (
	"reflect"
	"testing"

	"zabbix.com/pkg/std"
)

func TestFileExists(t *testing.T) {
	stdOs = std.NewMockOs()

	impl.options.Timeout = 3

	stdOs.(std.MockOs).MockFile("text.txt", []byte("1234"))
	if result, err := impl.Export("vfs.file.exists", []string{"text.txt"}, nil); err != nil {
		t.Errorf("vfs.file.exists returned error %s", err.Error())
	} else {
		if exists, ok := result.(int); !ok {
			t.Errorf("vfs.file.exists returned unexpected value type %s", reflect.TypeOf(result).Kind())
		} else {
			if exists != 1 {
				t.Errorf("vfs.file.exists returned invalid result")
			}
		}
	}
}

func TestFileNotExists(t *testing.T) {
	stdOs = std.NewMockOs()

	impl.options.Timeout = 3

	stdOs.(std.MockOs).MockFile("text.txt", []byte("1234"))
	if result, err := impl.Export("vfs.file.exists", []string{"text2.txt"}, nil); err != nil {
		t.Errorf("vfs.file.exists returned error %s", err.Error())
	} else {
		if exists, ok := result.(int); !ok {
			t.Errorf("vfs.file.exists returned unexpected value type %s", reflect.TypeOf(result).Kind())
		} else {
			if exists != 0 {
				t.Errorf("vfs.file.exists returned invalid result")
			}
		}
	}
}
