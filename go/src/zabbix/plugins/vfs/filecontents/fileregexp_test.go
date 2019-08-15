/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package filecontents

import (
	"reflect"
	"testing"
	"zabbix/internal/agent"
	"zabbix/pkg/std"
)

func TestFileRegexpOutput(t *testing.T) {
	stdOs = std.NewMockOs()

	agent.Options.Timeout = 3

	stdOs.(std.MockOs).MockFile("text.txt", []byte{0xe4, 0xd5, 0xde, 0xe4, 0xd0, 0xdd, 0x0d, 0x0a})
	if result, err := impl.Export("vfs.file.regexp", []string{"text.txt", "(ф)", "iso-8859-5", "", "", "group 0: \\0 group 1: \\1 group 4: \\4"}, nil); err != nil {
		t.Errorf("vfs.file.regexp returned error %s", err.Error())
	} else {
		if contents, ok := result.(string); !ok {
			t.Errorf("vfs.file.regexp returned unexpected value type %s", reflect.TypeOf(result).Kind())
		} else {
			if contents != "group 0: ф group 1: ф group 4: " {
				t.Errorf("vfs.file.regexp returned invalid result")
			}
		}
	}
}

func TestFileRegexp(t *testing.T) {
	stdOs = std.NewMockOs()

	agent.Options.Timeout = 3

	stdOs.(std.MockOs).MockFile("text.txt", []byte{0xd0, 0xd2, 0xd3, 0xe3, 0xe1, 0xe2, 0xd0, 0x0d, 0x0a})
	if result, err := impl.Export("vfs.file.regexp", []string{"text.txt", "(а)", "iso-8859-5", "", ""}, nil); err != nil {
		t.Errorf("vfs.file.regexp returned error %s", err.Error())
	} else {
		if contents, ok := result.(string); !ok {
			t.Errorf("vfs.file.regexp returned unexpected value type %s", reflect.TypeOf(result).Kind())
		} else {
			if contents != "августа" {
				t.Errorf("vfs.file.regexp returned invalid result")
			}
		}
	}
}
