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

var CrcFile = "1234"

func TestFileCksumDefault(t *testing.T) {
	stdOs = std.NewMockOs()

	impl.options.Timeout = 3

	stdOs.(std.MockOs).MockFile("text.txt", []byte(CrcFile))
	if result, err := impl.Export("vfs.file.cksum", []string{"text.txt"}, nil); err != nil {
		t.Errorf("vfs.file.cksum returned error %s", err.Error())
	} else {
		if crc, ok := result.(uint32); !ok {
			t.Errorf("vfs.file.cksum returned unexpected value type %s", reflect.TypeOf(result).Kind())
		} else {
			if crc != 3582362371 {
				t.Errorf("vfs.file.cksum returned invalid result")
			}
		}
	}
}

func TestFileCksumCrc32(t *testing.T) {
	stdOs = std.NewMockOs()

	impl.options.Timeout = 3

	stdOs.(std.MockOs).MockFile("text.txt", []byte(CrcFile))
	if result, err := impl.Export("vfs.file.cksum", []string{"text.txt", "crc32"}, nil); err != nil {
		t.Errorf("vfs.file.cksum[text.txt,crc32] returned error %s", err.Error())
	} else {
		if crc, ok := result.(uint32); !ok {
			t.Errorf("vfs.file.cksum[text.txt,crc32] returned unexpected value type %s", reflect.TypeOf(result).Kind())
		} else {
			if crc != 3582362371 {
				t.Errorf("vfs.file.cksum[text.txt,crc32] returned invalid result")
			}
		}
	}
}

func TestFileCksumMd5(t *testing.T) {
	stdOs = std.NewMockOs()

	impl.options.Timeout = 3

	stdOs.(std.MockOs).MockFile("text.txt", []byte(CrcFile))
	if result, err := impl.Export("vfs.file.cksum", []string{"text.txt", "md5"}, nil); err != nil {
		t.Errorf("vfs.file.cksum[text.txt,md5] returned error %s", err.Error())
	} else {
		if md5sum, ok := result.(string); !ok {
			t.Errorf("vfs.file.cksum[text.txt,md5] returned unexpected value type %s", reflect.TypeOf(result).Kind())
		} else {
			if md5sum != "81dc9bdb52d04dc20036dbd8313ed055" {
				t.Errorf("vfs.file.cksum[text.txt,md5] returned invalid result")
			}
		}
	}
}

func TestFileCksumSha256sum(t *testing.T) {
	stdOs = std.NewMockOs()

	impl.options.Timeout = 3

	stdOs.(std.MockOs).MockFile("text.txt", []byte(CrcFile))
	if result, err := impl.Export("vfs.file.cksum", []string{"text.txt", "sha256"}, nil); err != nil {
		t.Errorf("vfs.file.cksum[text.txt,sha256] returned error %s", err.Error())
	} else {
		if sha256, ok := result.(string); !ok {
			t.Errorf("vfs.file.cksum[text.txt,sha256] returned unexpected value type %s", reflect.TypeOf(result).Kind())
		} else {
			if sha256 != "03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4" {
				t.Errorf("vfs.file.cksum[text.txt,sha256] returned invalid result")
			}
		}
	}
}
