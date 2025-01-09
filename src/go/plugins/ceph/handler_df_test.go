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

package ceph

import (
	"encoding/json"
	"reflect"
	"testing"
)

func Test_dfHandler(t *testing.T) {
	out := outDf{
		Pools: map[string]poolStat{
			"device_health_metrics": {
				PercentUsed: 0.3,
				Objects:     0,
				BytesUsed:   0,
				Rd:          0,
				RdBytes:     0,
				Wr:          0,
				WrBytes:     0,
				StoredRaw:   0,
				MaxAvail:    1390298112,
			},
			"new_pool": {
				PercentUsed: 0.4,
				Objects:     0,
				BytesUsed:   0,
				Rd:          0,
				RdBytes:     0,
				Wr:          0,
				WrBytes:     0,
				StoredRaw:   0,
				MaxAvail:    695170880,
			},
			"test_zabbix": {
				PercentUsed: 0.00018851681670639664,
				Objects:     4,
				BytesUsed:   786432,
				Rd:          0,
				RdBytes:     0,
				Wr:          4,
				WrBytes:     24576,
				StoredRaw:   66618,
				MaxAvail:    1390298112,
			},
			"zabbix": {
				PercentUsed: 0,
				Objects:     0,
				BytesUsed:   0,
				Rd:          0,
				RdBytes:     0,
				Wr:          0,
				WrBytes:     0,
				StoredRaw:   0,
				MaxAvail:    1390298112,
			},
		},
		Rd:              0,
		RdBytes:         0,
		Wr:              4,
		WrBytes:         24576,
		NumPools:        4,
		TotalBytes:      12872318976,
		TotalAvailBytes: 6900023296,
		TotalUsedBytes:  2751070208,
		TotalObjects:    4,
	}

	success, err := json.Marshal(out)
	if err != nil {
		t.Fatal(err)
	}

	type args struct {
		data map[command][]byte
	}

	tests := []struct {
		name    string
		args    args
		want    interface{}
		wantErr bool
	}{
		{
			"Must parse an output of " + cmdDf + "command",
			args{map[command][]byte{cmdDf: fixtures[cmdDf]}},
			string(success),
			false,
		},
		{
			"Must fail on malformed input",
			args{map[command][]byte{cmdDf: fixtures[cmdBroken]}},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := dfHandler(tt.args.data)
			if (err != nil) != tt.wantErr {
				t.Errorf("dfHandler() error = %v, wantErr %v", err, tt.wantErr)

				return
			}

			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("dfHandler() got = %v, want %v", got, tt.want)
			}
		})
	}
}

func Benchmark_dfHandler(b *testing.B) {
	for i := 0; i < b.N; i++ {
		_, _ = dfHandler(map[command][]byte{cmdDf: fixtures[cmdDf]})
	}
}
