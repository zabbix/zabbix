//go:build linux
// +build linux

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

package procfs

import (
	"testing"
)

func BenchmarkReadAll(b *testing.B) {
	for i := 0; i < b.N; i++ {
		_, _ = ReadAll("/proc/self/stat")
	}
}

var testData = []byte(`
Name:	foo-bar
VmPeak:	    6032 kB
b:
VmHWM:	     456 mB
VmRSS:	     456 GB
VmData:	     376 TB
fail:		 abs TB
fail_type:	 200 FB
`)

func Test_byteFromProcFileData(t *testing.T) {
	type args struct {
		data      []byte
		valueName string
	}
	tests := []struct {
		name      string
		args      args
		wantValue uint64
		wantFound bool
		wantErr   bool
	}{
		{"+kB", args{testData, "VmPeak"}, 6032 * 1024, true, false},
		{"+mB", args{testData, "VmHWM"}, 456 * 1024 * 1024, true, false},
		{"+GB", args{testData, "VmRSS"}, 456 * 1024 * 1024 * 1024, true, false},
		{"+TB", args{testData, "VmData"}, 376 * 1024 * 1024 * 1024 * 1024, true, false},
		{"-malformed_line", args{testData, "b"}, 0, false, false},
		{"-incorrect_value", args{testData, "fail"}, 0, false, true},
		{"-incorrect_value_type", args{testData, "fail_type"}, 0, false, true},
		{"-not_found", args{testData, "FooBar"}, 0, false, false},
		{"-no_data", args{nil, ""}, 0, false, false},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gotValue, gotFound, err := ByteFromProcFileData(tt.args.data, tt.args.valueName)
			if (err != nil) != tt.wantErr {
				t.Errorf("ByteFromProcFileData() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if gotValue != tt.wantValue {
				t.Errorf("ByteFromProcFileData() gotValue = %v, want %v", gotValue, tt.wantValue)
			}
			if gotFound != tt.wantFound {
				t.Errorf("ByteFromProcFileData() gotFound = %v, want %v", gotFound, tt.wantFound)
			}
		})
	}
}
