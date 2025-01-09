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

func Test_osdHandler(t *testing.T) {
	out := outOsdStats{
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
			"Must parse an output of " + cmdPgDump + "command",
			args{map[command][]byte{cmdPgDump: fixtures[cmdPgDump]}},
			string(success),
			false,
		},
		{
			"Must fail on malformed input",
			args{map[command][]byte{cmdPgDump: fixtures[cmdBroken]}},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := osdHandler(tt.args.data)
			if (err != nil) != tt.wantErr {
				t.Errorf("osdHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("osdHandler() got = %v, want %v", got, tt.want)
			}
		})
	}
}

func Benchmark_osdHandler(b *testing.B) {
	for i := 0; i < b.N; i++ {
		_, _ = osdHandler(map[command][]byte{cmdPgDump: fixtures[cmdPgDump]})
	}
}

func Test_newAggDataFloat(t *testing.T) {
	type args struct {
		v []float64
	}
	tests := []struct {
		name string
		args args
		want aggDataFloat
	}{
		{
			"Must calculate correct aggregated data",
			args{[]float64{-999, 42, 124}},
			aggDataFloat{Min: -999, Max: 124, Avg: -277.6666666666667},
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := newAggDataFloat(tt.args.v); !reflect.DeepEqual(got, tt.want) {
				t.Errorf("newAggDataFloat() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_newAggDataInt(t *testing.T) {
	type args struct {
		v []uint64
	}
	tests := []struct {
		name string
		args args
		want aggDataInt
	}{
		{
			"Must calculate correct aggregated data",
			args{[]uint64{999, 42, 146}},
			aggDataInt{Min: 42, Max: 999, Avg: 395.6666666666667},
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := newAggDataInt(tt.args.v); !reflect.DeepEqual(got, tt.want) {
				t.Errorf("newAggDataInt() = %v, want %v", got, tt.want)
			}
		})
	}
}
