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
	_ "embed"
	"encoding/json"
	"testing"

	"github.com/google/go-cmp/cmp"
)

var (
	//go:embed testdata/status1.json
	testDataCephStatusOutput1 []byte

	//go:embed testdata/status2.json
	testDataCephStatusOutput2 []byte

	//go:embed testdata/status3.json
	testDataCephStatusOutput3 []byte
)

func Test_statusHandler(t *testing.T) {
	t.Parallel()

	toJSONString := func(v any) string {
		s, err := json.Marshal(v)
		if err != nil {
			t.Fatal(err)
		}

		return string(s)
	}

	type args struct {
		data map[command][]byte
	}

	tests := []struct {
		name    string
		args    args
		want    *outStatus
		wantErr bool
	}{
		{
			"+valid",
			args{map[command][]byte{cmdStatus: testDataCephStatusOutput1}},
			&outStatus{
				OverallStatus: 0,
				NumMon:        3,
				NumOsd:        3,
				NumOsdIn:      3,
				NumOsdUp:      3,
				NumPg:         33,
				PgStates: map[string]uint64{
					"activating":       0,
					"active":           33,
					"backfill_toofull": 0,
					"backfill_unfound": 0,
					"backfill_wait":    0,
					"backfilling":      0,
					"clean":            33,
					"creating":         0,
					"deep":             0,
					"degraded":         0,
					"down":             0,
					"forced_backfill":  0,
					"forced_recovery":  0,
					"incomplete":       0,
					"inconsistent":     0,
					"laggy":            0,
					"peered":           0,
					"peering":          0,
					"recovering":       0,
					"recovery_toofull": 0,
					"recovery_unfound": 0,
					"recovery_wait":    0,
					"remapped":         0,
					"repair":           0,
					"scrubbing":        0,
					"snaptrim":         0,
					"snaptrim_error":   0,
					"snaptrim_wait":    0,
					"stale":            0,
					"undersized":       0,
					"unknown":          0,
					"wait":             0,
				},
				MinMonReleaseName: "octopus",
			},
			false,
		},
		{
			"+valid2",
			args{map[command][]byte{cmdStatus: testDataCephStatusOutput2}},
			&outStatus{
				OverallStatus: 0,
				NumMon:        3,
				NumOsd:        283,
				NumOsdIn:      283,
				NumOsdUp:      283,
				NumPg:         6976,
				PgStates: map[string]uint64{
					"activating":       0,
					"active":           6976,
					"backfill_toofull": 0,
					"backfill_unfound": 0,
					"backfill_wait":    0,
					"backfilling":      0,
					"clean":            6976,
					"creating":         0,
					"deep":             1,
					"degraded":         0,
					"down":             0,
					"forced_backfill":  0,
					"forced_recovery":  0,
					"incomplete":       0,
					"inconsistent":     0,
					"laggy":            0,
					"peered":           0,
					"peering":          0,
					"recovering":       0,
					"recovery_toofull": 0,
					"recovery_unfound": 0,
					"recovery_wait":    0,
					"remapped":         0,
					"repair":           0,
					"scrubbing":        1,
					"snaptrim":         0,
					"snaptrim_error":   0,
					"snaptrim_wait":    0,
					"stale":            0,
					"undersized":       0,
					"unknown":          0,
					"wait":             0,
				},
				MinMonReleaseName: "",
			},
			false,
		},
		{
			"+valid3",
			args{map[command][]byte{cmdStatus: testDataCephStatusOutput3}},
			&outStatus{
				OverallStatus: 1,
				NumMon:        1,
				NumOsd:        1,
				NumOsdIn:      1,
				NumOsdUp:      0,
				NumPg:         1,
				PgStates: map[string]uint64{
					"activating":       0,
					"active":           0,
					"backfill_toofull": 0,
					"backfill_unfound": 0,
					"backfill_wait":    0,
					"backfilling":      0,
					"clean":            0,
					"creating":         0,
					"deep":             0,
					"degraded":         0,
					"down":             0,
					"forced_backfill":  0,
					"forced_recovery":  0,
					"incomplete":       0,
					"inconsistent":     0,
					"laggy":            0,
					"peered":           0,
					"peering":          0,
					"recovering":       0,
					"recovery_toofull": 0,
					"recovery_unfound": 0,
					"recovery_wait":    0,
					"remapped":         0,
					"repair":           0,
					"scrubbing":        0,
					"snaptrim":         0,
					"snaptrim_error":   0,
					"snaptrim_wait":    0,
					"stale":            0,
					"undersized":       0,
					"unknown":          1,
					"wait":             0,
				},
				MinMonReleaseName: "reef",
			},
			false,
		},
		{
			"-unmarshalErr",
			args{map[command][]byte{cmdStatus: []byte("{")}},
			nil,
			true,
		},
		{
			"-unknownHealth",
			args{
				map[command][]byte{
					cmdStatus: []byte(`{"health": {"status":"bannana"}}`),
				},
			},
			nil,
			true,
		},
		{
			"-unknownPGState",
			args{map[command][]byte{cmdStatus: []byte(
				`{
                    "health": {"status":"HEALTH_OK"},
                    "pgmap": {"pgs_by_state":[{"state_name":"bannan", "count": 3}]}
                 }`,
			)}},
			nil,
			true,
		},
		{
			"-noDataForNumMon",
			args{map[command][]byte{cmdStatus: []byte(
				`{
                    "health": {"status":"HEALTH_OK"}
                 }`,
			)}},
			nil,
			true,
		},
		{
			"-noDataForOSDMap",
			args{map[command][]byte{cmdStatus: []byte(
				`{
                    "health": {"status":"HEALTH_OK"},
                    "monmap": { "mons": [] }
                 }`,
			)}},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got, err := statusHandler(tt.args.data)
			if (err != nil) != tt.wantErr {
				t.Fatalf(
					"statusHandler() error = %v, wantErr %v",
					err,
					tt.wantErr,
				)
			}

			var want any

			if tt.want != nil {
				want = toJSONString(tt.want)
			}

			if diff := cmp.Diff(want, got); diff != "" {
				t.Fatalf("statusHandler() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}

func Benchmark_statusHandler(b *testing.B) {
	for i := 0; i < b.N; i++ {
		_, err := statusHandler(
			map[command][]byte{cmdStatus: testDataCephStatusOutput1},
		)
		if err != nil {
			b.Fatalf("statusHandler() error = %v", err)
		}
	}
}
