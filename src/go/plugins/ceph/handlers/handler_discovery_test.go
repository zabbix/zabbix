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

func TestOSDDiscoveryHandler(t *testing.T) {
	t.Parallel()

	wantSuccessStruct := []osdEntity{
		{"0", "hdd", "newbucket-host"},
		{"1", "hdd", "node2"},
		{"2", "hdd", "node3"},
	}

	testCases := []struct {
		name    string
		args    map[Command][]byte
		wantErr bool
	}{
		{
			name:    "+must return correct LLD rules for OSDs",
			args:    map[Command][]byte{cmdOSDCrushTree: fixtures[cmdOSDCrushTree]},
			wantErr: false,
		},
		{
			name:    "-must fail on malformed input",
			args:    map[Command][]byte{cmdOSDCrushTree: {1, 2, 3, 4, 5}},
			wantErr: true,
		},
	}

	for _, tc := range testCases {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			gotAny, err := osdDiscoveryHandler(tc.args)
			if (err != nil) != tc.wantErr {
				t.Fatalf("osdDiscoveryHandler() error = %v, wantErr %v", err, tc.wantErr)
			}

			if tc.wantErr {
				return
			}

			// Type-assert the result to a string.
			gotJSON, ok := gotAny.(string)
			if !ok {
				t.Fatalf("osdDiscoveryHandler() expected a string return type, but got %T", gotAny)
			}

			// Unmarshal the result and compare it to the expected struct.
			var gotStruct []osdEntity
			if err := json.Unmarshal([]byte(gotJSON), &gotStruct); err != nil {
				t.Fatalf("Failed to unmarshal result: %v", err)
			}

			if diff := cmp.Diff(wantSuccessStruct, gotStruct); diff != "" {
				t.Errorf("osdDiscoveryHandler() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}

func Benchmark_osdDiscoveryHandler(b *testing.B) {
	b.ReportAllocs()

	args := map[Command][]byte{
		cmdOSDCrushRuleDump: fixtures[cmdOSDCrushRuleDump],
		cmdOSDCrushTree:     fixtures[cmdOSDCrushTree],
	}

	b.ResetTimer()

	for range b.N {
		_, err := osdDiscoveryHandler(args)
		if err != nil {
			b.Fatalf("osdDiscoveryHandler() failed during benchmark: %v", err)
		}
	}
}

func Test_poolDiscoveryHandler(t *testing.T) {
	t.Parallel()

	wantSuccessStruct := []poolEntity{
		{"device_health_metrics", "default"},
		{"test_zabbix", "default"},
	}

	testCases := []struct {
		name    string
		args    map[Command][]byte
		wantErr bool
	}{
		{
			name: "+must return correct LLD rules for pools",
			args: map[Command][]byte{
				cmdOSDDump:          fixtures[cmdOSDDump],
				cmdOSDCrushRuleDump: fixtures[cmdOSDCrushRuleDump],
			},
			wantErr: false,
		},
		{
			name: "-must fail on malformed input",
			args: map[Command][]byte{
				cmdOSDDump:          {1, 2, 3, 4, 5},
				cmdOSDCrushRuleDump: fixtures[cmdOSDCrushRuleDump],
			},
			wantErr: true,
		},
		{
			name:    "-must fail if one of necessary Commands is absent",
			args:    map[Command][]byte{cmdOSDDump: fixtures[cmdBroken]},
			wantErr: true,
		},
	}

	for _, tc := range testCases {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			gotAny, err := poolDiscoveryHandler(tc.args)
			if (err != nil) != tc.wantErr {
				t.Fatalf("poolDiscoveryHandler() error = %v, wantErr %v", err, tc.wantErr)
			}

			if tc.wantErr {
				return
			}

			gotJSON, ok := gotAny.(string)
			if !ok {
				t.Fatalf("poolDiscoveryHandler() expected a string return type, but got %T", gotAny)
			}

			var gotStruct []poolEntity
			if err := json.Unmarshal([]byte(gotJSON), &gotStruct); err != nil {
				t.Fatalf("Failed to unmarshal result: %v", err)
			}

			if diff := cmp.Diff(wantSuccessStruct, gotStruct); diff != "" {
				t.Errorf("poolDiscoveryHandler() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}

func Benchmark_poolDiscoveryHandler(b *testing.B) {
	b.ReportAllocs()

	args := map[Command][]byte{
		cmdOSDDump:          fixtures[cmdOSDDump],
		cmdOSDCrushRuleDump: fixtures[cmdOSDCrushRuleDump],
	}

	b.ResetTimer()

	for range b.N {
		_, err := poolDiscoveryHandler(args)
		if err != nil {
			b.Fatalf("poolDiscoveryHandler() failed during benchmark: %v", err)
		}
	}
}
