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

package handlers

import (
	"encoding/json"
	"testing"

	"github.com/google/go-cmp/cmp"
	"github.com/google/go-cmp/cmp/cmpopts"
)

func Test_dfHandler(t *testing.T) {
	t.Parallel()

	wantSuccessStruct := outDf{
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

	testCases := []struct {
		name    string
		args    map[Command][]byte
		wantErr bool
	}{
		{
			name:    "+valid",
			args:    map[Command][]byte{cmdDf: fixtures[cmdDf]},
			wantErr: false,
		},
		{
			name:    "-malformedInput",
			args:    map[Command][]byte{cmdDf: fixtures[cmdBroken]},
			wantErr: true,
		},
	}

	for _, tc := range testCases {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			gotAny, err := dfHandler(tc.args)
			if (err != nil) != tc.wantErr {
				t.Fatalf("dfHandler() error = %v, wantErr %v", err, tc.wantErr)
			}

			if tc.wantErr {
				return
			}

			gotJSON, ok := gotAny.(string)
			if !ok {
				t.Fatalf("dfHandler() expected a string return type, but got %T", gotAny)
			}

			var gotStruct outDf
			if err := json.Unmarshal([]byte(gotJSON), &gotStruct); err != nil {
				t.Fatalf("Failed to unmarshal dfHandler() output: %v", err)
			}

			// Use an approximator for float comparisons to avoid precision issues.
			opts := cmpopts.EquateApprox(0, 1e-9)
			if diff := cmp.Diff(wantSuccessStruct, gotStruct, opts); diff != "" {
				t.Errorf("dfHandler() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}

func Benchmark_dfHandler(b *testing.B) {
	b.ReportAllocs()

	args := map[Command][]byte{cmdDf: fixtures[cmdDf]}

	b.ResetTimer()

	for range b.N {
		_, err := dfHandler(args)
		if err != nil {
			b.Fatalf("dfHandler() failed during benchmark: %v", err)
		}
	}
}
