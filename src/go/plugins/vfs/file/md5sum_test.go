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

var Md5File = "1234"

func TestFileMd5sum(t *testing.T) {
	stdOs = std.NewMockOs()

	impl.options.Timeout = 3

	stdOs.(std.MockOs).MockFile("text.txt", []byte(Md5File))
	if result, err := impl.Export("vfs.file.md5sum", []string{"text.txt"}, nil); err != nil {
		t.Errorf("vfs.file.md5sum returned error %s", err.Error())
	} else {
		if md5sum, ok := result.(string); !ok {
			t.Errorf("vfs.file.md5sum returned unexpected value type %s", reflect.TypeOf(result).Kind())
		} else {
			if md5sum != "81dc9bdb52d04dc20036dbd8313ed055" {
				t.Errorf("vfs.file.md5sum returned invalid result")
			}
		}
	}
}
