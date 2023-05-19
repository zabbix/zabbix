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
	//	"fmt"
	//"io/ioutil"
)

func TestFileRegmatch(t *testing.T) {

	impl.options.Timeout = 3

	d1 := []byte{0xd0, 0xd2, 0xd3, 0xe3, 0xe1, 0xe2, 0xd0, 0x0d, 0x0a}

	if err1 := os.WriteFile("/tmp/zbx_vfs_file_regmatch_test.dat", d1, 0644); err1 != nil {
		t.Errorf("failed to created file: %s", err1.Error())
		return
	}

	if result, err := impl.Export("vfs.file.regmatch", []string{"/tmp/zbx_vfs_file_regmatch_test.dat", "(а)", "iso-8859-5", "", ""}, nil); err != nil {
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

	impl.options.Timeout = 3

	/* file with 3 lines, encoded in utf16le:
	   badger
	   error
	   badger2 */

	f1 := []byte{0x62, 0x00, 0x61, 0x00, 0x64, 0x00, 0x67, 0x00, 0x65, 0x00, 0x72, 0x00, 0x0a, 0x00, 0x65, 0x00, 0x72, 0x00, 0x72, 0x00, 0x6f, 0x00, 0x72, 0x00, 0x0a, 0x00, 0x62, 0x00, 0x61, 0x00, 0x64, 0x00, 0x67, 0x00, 0x65, 0x00, 0x72, 0x00, 0x32, 0x00, 0x0a, 0x00}

	if err1 := os.WriteFile("/tmp/vfs_file_regmatch_test_error_utf16le.dat", f1, 0644); err1 != nil {
		t.Errorf("failed to created file: %s", err1.Error())
	}

	if result, err := impl.Export("vfs.file.regmatch", []string{"/tmp/vfs_file_regmatch_test_error_utf16le.dat", "error", "UTF16LE", "", ""}, nil); err != nil {
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
