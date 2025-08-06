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

func Test_osdDumpHandler(t *testing.T) {
	t.Parallel()

	wantSuccessStruct := outOsdDump{
		BackfillFullRatio: 0.9,
		FullRatio:         0.95,
		NearFullRatio:     0.85,
		NumPgTemp:         0,
		Osds: map[string]osdStatus{
			"0": {In: 1, Up: 1},
			"1": {In: 1, Up: 1},
			"2": {In: 1, Up: 1},
		},
	}

	testCases := []struct {
		name    string
		args    map[Command][]byte
		wantErr bool
	}{
		{
			name:    "+ok",
			args:    map[Command][]byte{cmdOSDDump: fixtures[cmdOSDDump]},
			wantErr: false,
		},
		{
			name:    "-malformedInput",
			args:    map[Command][]byte{cmdOSDDump: fixtures[cmdBroken]},
			wantErr: true,
		},
	}

	for _, tc := range testCases {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			gotAny, err := osdDumpHandler(tc.args)
			if (err != nil) != tc.wantErr {
				t.Fatalf("osdDumpHandler() error = %v, wantErr %v", err, tc.wantErr)
			}

			if tc.wantErr {
				return
			}

			gotJSON, ok := gotAny.(string)
			if !ok {
				t.Fatalf("osdDumpHandler() expected a string return type, but got %T", gotAny)
			}

			var gotStruct outOsdDump
			if err := json.Unmarshal([]byte(gotJSON), &gotStruct); err != nil {
				t.Fatalf("Failed to unmarshal osdDumpHandler() output: %v", err)
			}

			opts := cmpopts.EquateApprox(0, 1e-9)

			if diff := cmp.Diff(wantSuccessStruct, gotStruct, opts); diff != "" {
				t.Errorf("osdDumpHandler() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}
