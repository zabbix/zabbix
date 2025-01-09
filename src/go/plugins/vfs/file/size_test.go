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

func TestFileSize(t *testing.T) {
	stdOs = std.NewMockOs()

	stdOs.(std.MockOs).MockFile("text.txt", []byte("1234"))
	if result, err := impl.Export("vfs.file.size", []string{"text.txt"}, nil); err != nil {
		t.Errorf("vfs.file.size returned error %s", err.Error())
	} else {
		if filesize, ok := result.(int64); !ok {
			t.Errorf("vfs.file.size returned unexpected value type %s", reflect.TypeOf(result).Kind())
		} else {
			if filesize != 4 {
				t.Errorf("vfs.file.size returned invalid result")
			}
		}
	}
}
