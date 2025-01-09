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

var CrcFile = "1234"

func TestFileCksumDefault(t *testing.T) {
	var ctx zbxtest.MockEmptyCtx
	stdOs = std.NewMockOs()

	stdOs.(std.MockOs).MockFile("text.txt", []byte(CrcFile))

	if result, err := impl.Export("vfs.file.cksum", []string{"text.txt"}, ctx); err != nil {
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
	var ctx zbxtest.MockEmptyCtx
	stdOs = std.NewMockOs()

	stdOs.(std.MockOs).MockFile("text.txt", []byte(CrcFile))
	if result, err := impl.Export("vfs.file.cksum", []string{"text.txt", "crc32"}, ctx); err != nil {
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
	var ctx zbxtest.MockEmptyCtx
	stdOs = std.NewMockOs()

	stdOs.(std.MockOs).MockFile("text.txt", []byte(CrcFile))
	if result, err := impl.Export("vfs.file.cksum", []string{"text.txt", "md5"}, ctx); err != nil {
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
	var ctx zbxtest.MockEmptyCtx
	stdOs = std.NewMockOs()

	stdOs.(std.MockOs).MockFile("text.txt", []byte(CrcFile))
	if result, err := impl.Export("vfs.file.cksum", []string{"text.txt", "sha256"}, ctx); err != nil {
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
