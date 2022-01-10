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

package ceph

import (
	"encoding/json"
	"reflect"
	"testing"
)

func Test_statusHandler(t *testing.T) {
	out := outStatus{OverallStatus: 0,
		NumMon:   3,
		NumOsd:   3,
		NumOsdIn: 3,
		NumOsdUp: 3,
		NumPg:    33,
		PgStates: map[string]uint64{"activating": 0, "active": 33, "backfill_toofull": 0, "backfill_unfound": 0,
			"backfill_wait": 0, "backfilling": 0, "clean": 33, "creating": 0, "deep": 0, "degraded": 0, "down": 0,
			"forced_backfill": 0, "forced_recovery": 0, "incomplete": 0, "inconsistent": 0, "laggy": 0, "peered": 0,
			"peering": 0, "recovering": 0, "recovery_toofull": 0, "recovery_unfound": 0, "recovery_wait": 0,
			"remapped": 0, "repair": 0, "scrubbing": 0, "snaptrim": 0, "snaptrim_error": 0, "snaptrim_wait": 0,
			"stale": 0, "undersized": 0, "unknown": 0, "wait": 0},
		MinMonReleaseName: "octopus"}

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
			"Must parse an output of " + cmdStatus + "command",
			args{map[command][]byte{cmdStatus: fixtures[cmdStatus]}},
			string(success),
			false,
		},
		{
			"Must fail on malformed input",
			args{map[command][]byte{cmdStatus: fixtures[cmdBroken]}},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := statusHandler(tt.args.data)
			if (err != nil) != tt.wantErr {
				t.Errorf("statusHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("statusHandler() got = %v, want %v", got, tt.want)
			}
		})
	}
}

func Benchmark_statusHandler(b *testing.B) {
	for i := 0; i < b.N; i++ {
		_, _ = statusHandler(map[command][]byte{cmdStatus: fixtures[cmdStatus]})
	}
}
