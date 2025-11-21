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

package netif

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/google/go-cmp/cmp"
)

func TestNetif(t *testing.T) { //nolint:tparallel //cannot run in parallel due to primitive mocking.
	t.Parallel()

	type args struct {
		key    string
		params []string
	}

	//nolint:lll // this is a file output
	const contentNetif02 = `Inter-|   Receive                                                |  Transmit
face |bytes    packets errs drop fifo frame compressed multicast|bytes    packets errs drop fifo colls carrier compressed
   lo: 2897093   11757    0    0    0     0          0         0  2897093   11757    0    0    0     0       0          0
 eno1: 709017493  620061   15    7    1   345        500     16001 22780124  241308   87 1234    5   543       2        100
 eno2: 709017493  abc   15    7    1   345        500     16001 22780124  241308   87 1234    5   543       x        100
  lo1  2897093   11757    0    0    0     0          0         0  2897093   11757    0    0    0     0       0          0
eno3: 709017493  620061   15    7    1   345        500     16001 22780124  241308   87 1234    5   543       2`

	tests := []struct {
		name        string
		fileContent string
		args        args
		want        any
		wantErr     bool
	}{
		// testNetif01 (Empty file)
		{
			name:        "-InEmptyFile",
			fileContent: "",
			args: args{
				key:    "net.if.in",
				params: []string{"eno1", "bytes"},
			},
			want:    uint64(0),
			wantErr: true,
		},
		{
			name:        "+DiscoveryEmptyFile",
			fileContent: "",
			args: args{
				key:    "net.if.discovery",
				params: []string{},
			},
			want:    "[]",
			wantErr: false,
		},
		{
			name:        "-CollisionsEmptyFile",
			fileContent: "",
			args: args{
				key:    "net.if.collisions",
				params: []string{"eno1"},
			},
			want:    uint64(0),
			wantErr: true,
		},

		// testNetif02 (Valid content with some errors)
		{
			name:        "-InEno2PacketsDataMissing",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.in",
				params: []string{"eno2", "packets"},
			},
			want:    uint64(0),
			wantErr: true,
		},
		{
			name:        "-CollisionsMissingInterface",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.collisions",
				params: []string{},
			},
			want:    nil,
			wantErr: true,
		},
		{
			name:        "-CollisionsInvalidParamBytes",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.collisions",
				params: []string{"eno1", "bytes"},
			},
			want:    nil,
			wantErr: true,
		},
		{
			name:        "-CollisionsEmptyMode",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.collisions",
				params: []string{"eno1", ""},
			},
			want:    nil,
			wantErr: true,
		},
		{
			name:        "-CollisionsUnknownInterface",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.collisions",
				params: []string{"invalid1"},
			},
			want:    uint64(0),
			wantErr: true,
		},
		{
			name:        "+CollisionsEno1",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.collisions",
				params: []string{"eno1"},
			},
			want:    uint64(543),
			wantErr: false,
		},
		{
			name:        "+CollisionsLo",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.collisions",
				params: []string{"lo"},
			},
			want:    uint64(0),
			wantErr: false,
		},
		{
			name:        "-InMissingInterface",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.in",
				params: []string{},
			},
			want:    nil,
			wantErr: true,
		},
		{
			name:        "-InTooManyParams",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.in",
				params: []string{"eno1", "bytes", "something"},
			},
			want:    nil,
			wantErr: true,
		},
		{
			name:        "-InUnknownInterface",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.in",
				params: []string{"invalid1"},
			},
			want:    uint64(0),
			wantErr: true,
		},
		{
			name:        "-InInvalidMode",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.in",
				params: []string{"eno1", "b"},
			},
			want:    uint64(0),
			wantErr: true,
		},
		{
			name:        "+InEno1Bytes",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.in",
				params: []string{"eno1", "bytes"},
			},
			want:    uint64(709017493),
			wantErr: false,
		},
		{
			name:        "+InEno1EmptyMode",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.in",
				params: []string{"eno1", ""},
			},
			want:    uint64(709017493),
			wantErr: false,
		},
		{
			name:        "+InEno1Default",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.in",
				params: []string{"eno1"},
			},
			want:    uint64(709017493),
			wantErr: false,
		},
		{
			name:        "+InEno1Errors",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.in",
				params: []string{"eno1", "errors"},
			},
			want:    uint64(15),
			wantErr: false,
		},
		{
			name:        "+InLoPackets",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.in",
				params: []string{"lo", "packets"},
			},
			want:    uint64(11757),
			wantErr: false,
		},
		{
			name:        "+OutEno1",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.out",
				params: []string{"eno1"},
			},
			want:    uint64(22780124),
			wantErr: false,
		},
		{
			name:        "+OutEno1Packets",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.out",
				params: []string{"eno1", "packets"},
			},
			want:    uint64(241308),
			wantErr: false,
		},
		{
			name:        "+OutEno1Dropped",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.out",
				params: []string{"eno1", "dropped"},
			},
			want:    uint64(1234),
			wantErr: false,
		},
		{
			name:        "+OutLoDropped",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.out",
				params: []string{"lo", "dropped"},
			},
			want:    uint64(0),
			wantErr: false,
		},
		{
			name:        "+OutEno1Carrier",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.out",
				params: []string{"eno1", "carrier"},
			},
			want:    uint64(2),
			wantErr: false,
		},
		{
			name:        "+OutEno1Compressed",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.out",
				params: []string{"eno1", "compressed"},
			},
			want:    uint64(100),
			wantErr: false,
		},
		{
			name:        "-TotalMissingInterface",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.total",
				params: []string{},
			},
			want:    nil,
			wantErr: true,
		},
		{
			name:        "+TotalEno1Bytes",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.total",
				params: []string{"eno1", "bytes"},
			},
			want:    uint64(731797617),
			wantErr: false,
		},
		{
			name:        "+TotalEno1",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.total",
				params: []string{"eno1"},
			},
			want:    uint64(731797617),
			wantErr: false,
		},
		{
			name:        "+TotalEno1Overruns",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.total",
				params: []string{"eno1", "overruns"},
			},
			want:    uint64(6),
			wantErr: false,
		},
		{
			name:        "+TotalEno1Compressed",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.total",
				params: []string{"eno1", "compressed"},
			},
			want:    uint64(600),
			wantErr: false,
		},
		{
			name:        "+TotalLoPackets",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.total",
				params: []string{"lo", "packets"},
			},
			want:    uint64(23514),
			wantErr: false,
		},
		{
			name:        "+InEno1Multicast",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.in",
				params: []string{"eno1", "multicast"},
			},
			want:    uint64(16001),
			wantErr: false,
		},
		{
			name:        "+InLoFrame",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.in",
				params: []string{"lo", "frame"},
			},
			want:    uint64(0),
			wantErr: false,
		},
		{
			name:        "-InEmptyInterface",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.in",
				params: []string{""},
			},
			want:    nil,
			wantErr: true,
		},
		{
			name:        "-InEmptyInterfaceBytes",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.in",
				params: []string{"", "bytes"},
			},
			want:    nil,
			wantErr: true,
		},
		{
			name:        "-CollisionsEmptyInterface",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.collisions",
				params: []string{""},
			},
			want:    nil,
			wantErr: true,
		},
		{
			name:        "-InUnknownInterfaceLo1",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.in",
				params: []string{"lo1", "packets"},
			},
			want:    uint64(0),
			wantErr: true,
		},
		{
			name:        "-InEno2PacketsDataMissing",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.in",
				params: []string{"eno2", "packets"},
			},
			want:    uint64(0),
			wantErr: true,
		},
		{
			name:        "-OutEno2CarrierMissing",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.out",
				params: []string{"eno2", "carrier"},
			},
			want:    uint64(0),
			wantErr: true,
		},
		{
			name:        "-TotalEno2PacketsMissing",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.total",
				params: []string{"eno2", "packets"},
			},
			want:    uint64(0),
			wantErr: true,
		},
		{
			name:        "+OutEno2Packets",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.out",
				params: []string{"eno2", "packets"},
			},
			want:    uint64(241308),
			wantErr: false,
		},
		{
			name:        "-CollisionsEno3Missing",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.collisions",
				params: []string{"eno3"},
			},
			want:    uint64(0),
			wantErr: true,
		},
		{
			name:        "-InEno3BytesMissing",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.in",
				params: []string{"eno3", "bytes"},
			},
			want:    uint64(0),
			wantErr: true,
		},
		{
			name:        "-OutEno1InvalidModec",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.out",
				params: []string{"eno1", "c"},
			},
			want:    uint64(0),
			wantErr: true,
		},
		{
			name:        "-DiscoveryWithParams",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.discovery",
				params: []string{"eno1"},
			},
			want:    nil,
			wantErr: true,
		},
		{
			name:        "+DiscoveryFull",
			fileContent: contentNetif02,
			args: args{
				key:    "net.if.discovery",
				params: []string{},
			},
			want:    "[{\"{#IFNAME}\":\"lo\"},{\"{#IFNAME}\":\"eno1\"},{\"{#IFNAME}\":\"eno2\"},{\"{#IFNAME}\":\"eno3\"}]", //nolint:lll //this is long expected result
			wantErr: false,
		},
		{
			name:        "-UnknownKey",
			fileContent: contentNetif02,
			args: args{
				key:    "wrong.key",
				params: []string{},
			},
			want:    nil,
			wantErr: true,
		},

		// testNetif03 (Empty file)
		{
			name:        "-CollisionsEmptyFile2",
			fileContent: "",
			args: args{
				key:    "net.if.collisions",
				params: []string{"eno1"},
			},
			want:    uint64(0),
			wantErr: true,
		},
		{
			name:        "-InEmptyFile2",
			fileContent: "",
			args: args{
				key:    "net.if.in",
				params: []string{"eno1", "bytes"},
			},
			want:    uint64(0),
			wantErr: true,
		},
		{
			name:        "+DiscoveryEmptyFile2",
			fileContent: "",
			args: args{
				key:    "net.if.discovery",
				params: []string{},
			},
			want:    "[]",
			wantErr: false,
		},
	}

	for _, tt := range tests { //nolint:paralleltest // cannot test due to primitive mocking.
		t.Run(tt.name, func(t *testing.T) {
			// Create a temporary directory and file for each test case
			dir := t.TempDir()
			fn := filepath.Join(dir, "dev")

			err := os.WriteFile(fn, []byte(tt.fileContent), 0600)
			if err != nil {
				t.Fatalf("failed to create temp file: %v", err)
			}

			// Mock the file path global variable
			old := netDevFilepath
			netDevFilepath = fn

			t.Cleanup(func() {
				netDevFilepath = old
			})

			got, err := impl.Export(tt.args.key, tt.args.params, nil)
			if (err != nil) != tt.wantErr {
				t.Fatalf("Export() error = %v, wantErr %v", err, tt.wantErr)

				return
			}

			diff := cmp.Diff(tt.want, got)
			if diff != "" {
				t.Fatalf("Export() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}
