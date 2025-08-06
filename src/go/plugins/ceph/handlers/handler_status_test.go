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

	testCases := []struct {
		name    string
		args    map[Command][]byte
		want    *outStatus
		wantErr bool
	}{
		{
			name: "+valid",
			args: map[Command][]byte{cmdStatus: testDataCephStatusOutput1},
			want: &outStatus{
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
			wantErr: false,
		},
		{
			name: "+valid2",
			args: map[Command][]byte{cmdStatus: testDataCephStatusOutput2},
			want: &outStatus{
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
			wantErr: false,
		},
		{
			name: "+valid3",
			args: map[Command][]byte{cmdStatus: testDataCephStatusOutput3},
			want: &outStatus{
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
			wantErr: false,
		},
		{
			name:    "-unmarshalErr",
			args:    map[Command][]byte{cmdStatus: []byte("{")},
			want:    nil,
			wantErr: true,
		},
		{
			name: "-unknownHealth",
			args: map[Command][]byte{
				cmdStatus: []byte(`{"health": {"status":"bannana"}}`)},
			want:    nil,
			wantErr: true,
		},
		{
			name: "-unknownPGState",
			args: map[Command][]byte{cmdStatus: []byte(
				`{
                    "health": {"status":"HEALTH_OK"},
                    "pgmap": {"pgs_by_state":[{"state_name":"bannan", "count": 3}]}
                 }`,
			)},
			want:    nil,
			wantErr: true,
		},
		{
			name: "-noDataForNumMon",
			args: map[Command][]byte{cmdStatus: []byte(
				`{
                    "health": {"status":"HEALTH_OK"}
                 }`,
			)},
			want:    nil,
			wantErr: true,
		},
		{
			name: "-noDataForOSDMap",
			args: map[Command][]byte{cmdStatus: []byte(
				`{
                    "health": {"status":"HEALTH_OK"},
                    "monmap": { "mons": [] }
                 }`,
			)},
			want:    nil,
			wantErr: true,
		},
	}

	for _, tc := range testCases {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			got, err := statusHandler(tc.args)
			if (err != nil) != tc.wantErr {
				t.Fatalf("statusHandler() error = %v, wantErr %v", err, tc.wantErr)
			}

			// If an error was expected and occurred, no further checks are needed.
			if tc.wantErr {
				return
			}

			status, _ := json.Marshal(*tc.want)

			if diff := cmp.Diff(string(status), got); diff != "" {
				t.Errorf("statusHandler() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}

func Benchmark_statusHandler(b *testing.B) {
	b.ReportAllocs()

	for range b.N {
		_, err := statusHandler(
			map[Command][]byte{cmdStatus: testDataCephStatusOutput1},
		)
		if err != nil {
			b.Fatal(err)
		}
	}
}
