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

package maxfiles

import (
	"reflect"
	"testing"
	"zabbix/pkg/std"
)

var fileMax = "2612488\n"
var fileMaxInvalid = "2612488"

func TestMaxfiles(t *testing.T) {
	stdOs = std.NewMockOs()

	stdOs.(std.MockOs).MockFile("/proc/sys/fs/file-max", []byte(fileMax))

	if result, err := impl.Export("kernel.maxfiles", []string{}); err != nil {
		t.Errorf("kernel.maxfiles returned error %s", err.Error())
	} else {
		if maxfiles, ok := result.(uint64); !ok {
			t.Errorf("kernel.maxfiles returned unexpected value type %s", reflect.TypeOf(result).Kind())
		} else {
			if uint64(maxfiles) != 2612488 {
				t.Errorf("kernel.maxfiles returned invalid result: %d", result)
			}
		}
	}

	stdOs.(std.MockOs).MockFile("/proc/sys/fs/file-max", []byte(fileMaxInvalid))

	if result, err := impl.Export("kernel.maxproc", []string{}); err == nil {
		t.Errorf("kernel.maxfiles returned %d", result)
	}
}
