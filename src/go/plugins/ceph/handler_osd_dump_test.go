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

func Test_osdDumpHandler(t *testing.T) {
	out := outOsdDump{
		BackfillFullRatio: 0.9,
		FullRatio:         0.95,
		NearFullRatio:     0.85,
		NumPgTemp:         0,
		Osds: map[string]osdStatus{
			"0": {
				In: 1,
				Up: 1,
			},
			"1": {
				In: 1,
				Up: 1,
			},
			"2": {
				In: 1,
				Up: 1,
			},
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
			"Must parse an output of " + cmdOSDDump + "command",
			args{map[command][]byte{cmdOSDDump: fixtures[cmdOSDDump]}},
			string(success),
			false,
		},
		{
			"Must fail on malformed input",
			args{map[command][]byte{cmdOSDDump: fixtures[cmdBroken]}},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := osdDumpHandler(tt.args.data)
			if (err != nil) != tt.wantErr {
				t.Errorf("osdDumpHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("osdDumpHandler() got = %v, want %v", got, tt.want)
			}
		})
	}
}

func Benchmark_osdDumpHandler(b *testing.B) {
	for i := 0; i < b.N; i++ {
		_, _ = osdDumpHandler(map[command][]byte{cmdOSDDump: fixtures[cmdOSDDump]})
	}
}
