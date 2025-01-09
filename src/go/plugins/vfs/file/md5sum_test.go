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

	"golang.zabbix.com/agent2/pkg/zbxtest"
	"golang.zabbix.com/sdk/std"
)

var Md5File = "1234"

func TestFileMd5sum(t *testing.T) {
	var ctx zbxtest.MockEmptyCtx
	stdOs = std.NewMockOs()

	stdOs.(std.MockOs).MockFile("text.txt", []byte(Md5File))
	if result, err := impl.Export("vfs.file.md5sum", []string{"text.txt"}, ctx); err != nil {
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
