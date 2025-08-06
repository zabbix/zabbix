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
)

func Test_osdHandler(t *testing.T) {
	t.Parallel()

	wantSuccessStruct := outOsdStats{
		LatencyApply:  aggDataInt{Min: 0, Max: 1, Avg: 0.6666666666666666},
		LatencyCommit: aggDataInt{Min: 0, Max: 1, Avg: 0.6666666666666666},
		Fill:          aggDataFloat{Min: 28, Max: 28, Avg: 28},
		Pgs:           aggDataInt{Min: 1, Max: 1, Avg: 1},
		Osds: map[string]osdStat{
			"0": {LatencyApply: 0, LatencyCommit: 0, NumPgs: 1, OsdFill: 28},
			"1": {LatencyApply: 1, LatencyCommit: 1, NumPgs: 1, OsdFill: 28},
			"2": {LatencyApply: 1, LatencyCommit: 1, NumPgs: 1, OsdFill: 28},
		},
	}

	wantSuccessJSON, err := json.Marshal(wantSuccessStruct)
	if err != nil {
		t.Fatalf("Failed to marshal expected output: %v", err)
	}

	testCases := []struct {
		name    string
		args    map[Command][]byte
		want    string
		wantErr bool
	}{
		{
			name:    "+ok",
			args:    map[Command][]byte{cmdPgDump: fixtures[cmdPgDump]},
			want:    string(wantSuccessJSON),
			wantErr: false,
		},
		{
			name:    "-malformedInput",
			args:    map[Command][]byte{cmdPgDump: fixtures[cmdBroken]},
			want:    "", // No output is expected on error.
			wantErr: true,
		},
	}

	for _, tc := range testCases {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			got, err := osdHandler(tc.args)
			if (err != nil) != tc.wantErr {
				t.Fatalf("osdHandler() error = %v, wantErr %v", err, tc.wantErr)
			}

			if tc.wantErr {
				return
			}

			if diff := cmp.Diff(tc.want, got); diff != "" {
				t.Errorf("osdHandler() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}

func Benchmark_osdHandler(b *testing.B) {
	b.ReportAllocs()

	args := map[Command][]byte{cmdPgDump: fixtures[cmdPgDump]}

	b.ResetTimer() // Don't include setup in the benchmark time.

	for range b.N {
		_, err := osdHandler(args)
		if err != nil {
			b.Fatalf("osdHandler() failed during benchmark: %v", err)
		}
	}
}

func Test_newAggDataFloat(t *testing.T) {
	t.Parallel()

	testCases := []struct {
		name string
		args []float64
		want aggDataFloat
	}{
		{
			name: "CalculatesCorrectAggregatedData",
			args: []float64{-999, 42, 124},
			want: aggDataFloat{Min: -999, Max: 124, Avg: -277.6666666666667},
		},
	}

	for _, tc := range testCases {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			got := newAggDataFloat(tc.args)
			if diff := cmp.Diff(tc.want, got); diff != "" {
				t.Errorf("newAggDataFloat() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}

func Test_newAggDataInt(t *testing.T) {
	t.Parallel()

	testCases := []struct {
		name string
		args []uint64
		want aggDataInt
	}{
		{
			name: "CalculatesCorrectAggregatedData",
			args: []uint64{999, 42, 146},
			want: aggDataInt{Min: 42, Max: 999, Avg: 395.6666666666667},
		},
	}

	for _, tc := range testCases {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			got := newAggDataInt(tc.args)
			if diff := cmp.Diff(tc.want, got); diff != "" {
				t.Errorf("newAggDataInt() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}
