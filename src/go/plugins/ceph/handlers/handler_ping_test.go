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
	"testing"

	"github.com/google/go-cmp/cmp"
)

func Test_pingHandler(t *testing.T) {
	t.Parallel()

	testCases := []struct {
		name    string
		args    map[Command][]byte
		want    int
		wantErr bool
	}{
		{
			name:    "+valid",
			args:    map[Command][]byte{cmdHealth: fixtures[cmdHealth]},
			want:    PingOk,
			wantErr: false,
		},
		{
			name:    "+failed",
			args:    map[Command][]byte{cmdHealth: fixtures[cmdBroken]},
			want:    PingFailed,
			wantErr: false,
		},
	}

	for _, tc := range testCases {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			got, err := pingHandler(tc.args)
			if (err != nil) != tc.wantErr {
				t.Fatalf("pingHandler() error = %v, wantErr %v", err, tc.wantErr)
			}

			if diff := cmp.Diff(tc.want, got); diff != "" {
				t.Errorf("pingHandler() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}
