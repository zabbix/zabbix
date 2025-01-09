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

func TestOSDDiscoveryHandler(t *testing.T) {
	out := []osdEntity{
		{"0", "hdd", "newbucket-host"},
		{"1", "hdd", "node2"},
		{"2", "hdd", "node3"},
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
			"Must return correct LLD rules for OSDs",
			args{map[command][]byte{
				cmdOSDCrushTree: fixtures[cmdOSDCrushTree],
			}},
			string(success),
			false,
		},
		{
			"Must fail on malformed input",
			args{map[command][]byte{
				cmdOSDCrushTree: {1, 2, 3, 4, 5},
			}},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := osdDiscoveryHandler(tt.args.data)
			if (err != nil) != tt.wantErr {
				t.Errorf("osdDiscoveryHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("osdDiscoveryHandler() got = %v, want %v", got, tt.want)
			}
		})
	}
}

func Benchmark_osdDiscoveryHandler(b *testing.B) {
	for i := 0; i < b.N; i++ {
		_, _ = osdDiscoveryHandler(map[command][]byte{
			cmdOSDCrushRuleDump: fixtures[cmdOSDCrushRuleDump],
			cmdOSDCrushTree:     fixtures[cmdOSDCrushTree],
		})
	}
}

func Test_poolDiscoveryHandler(t *testing.T) {
	out := []poolEntity{
		{"device_health_metrics", "default"},
		{"test_zabbix", "default"},
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
			"Must return correct LLD rules for pools",
			args{map[command][]byte{
				cmdOSDDump:          fixtures[cmdOSDDump],
				cmdOSDCrushRuleDump: fixtures[cmdOSDCrushRuleDump],
			}},
			string(success),
			false,
		},
		{
			"Must fail on malformed input",
			args{map[command][]byte{
				cmdOSDDump:          {1, 2, 3, 4, 5},
				cmdOSDCrushRuleDump: fixtures[cmdOSDCrushRuleDump],
			}},
			nil,
			true,
		},
		{
			"Must fail if one of necessary commands is absent",
			args{map[command][]byte{cmdOSDDump: fixtures[cmdBroken]}},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := poolDiscoveryHandler(tt.args.data)
			if (err != nil) != tt.wantErr {
				t.Errorf("poolDiscoveryHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("poolDiscoveryHandler() got = %v, want %v", got, tt.want)
			}
		})
	}
}

func Benchmark_poolDiscoveryHandler(b *testing.B) {
	for i := 0; i < b.N; i++ {
		_, _ = poolDiscoveryHandler(map[command][]byte{
			cmdOSDDump:          fixtures[cmdOSDDump],
			cmdOSDCrushRuleDump: fixtures[cmdOSDCrushRuleDump],
		})
	}
}
