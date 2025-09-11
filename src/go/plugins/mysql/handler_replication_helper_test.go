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

package mysql

import (
	"testing"

	"github.com/google/go-cmp/cmp"
)

func Test_getReplicationQuery(t *testing.T) {
	t.Parallel()

	type args = struct {
		threshold string
		vers      string
	}

	tests := []struct {
		name    string
		args    args
		want    replicaQuery
		wantErr bool
	}{
		{
			"+equal",
			args{"8.4", "8.4"},
			replicaQueryNew,
			false,
		},
		{
			"+patchEqual",
			args{"8.4", "8.4.0"},
			replicaQueryNew,
			false,
		},
		{
			"+RcEqual",
			args{"8.4.1-rc.2", "8.4.1-rc.2"},
			replicaQueryNew,
			false,
		},
		{
			"+lower",
			args{"8.4", "8.3"},
			replicaQueryOld,
			false,
		},

		{
			"+emptyLower", //todo trim in function
			args{"8.4", "  "},
			replicaQueryOld,
			false,
		},
		{
			"+RcLower",
			args{"8.4.1-rc.2", "8.4.1-rc.1"},
			replicaQueryOld,
			false,
		},
		{
			"+majorLower",
			args{"8.4", "7"},
			replicaQueryOld,
			false,
		},
		{
			"+alphaLower",
			args{"8.4.1", "8.4.1-alpha"},
			replicaQueryOld,
			false,
		},
		{
			"+higher",
			args{"8.4", "8.5"},
			replicaQueryNew,
			false,
		},
		{
			"+majorHigher",
			args{"8.4", "9"},
			replicaQueryNew,
			false,
		},
		{
			"+rcMajorHigher",
			args{"8.2.0-rc.1", "8.2"},
			replicaQueryNew,
			false,
		},
		{
			"+alphaNumHigher",
			args{"8.2.0-alpha", "8.2.0-alpha.1"},
			replicaQueryNew,
			false,
		},
		{
			"+buildHigher",
			args{"8.2.0+1234", "8.2.0+1235"},
			replicaQueryNew,
			false,
		},
		{
			"-rubbish",
			args{"8.4", "rubbish"},
			"",
			true,
		},
		{
			"-withPrefix",
			args{"8.4", "v8.5"},
			"",
			true,
		},
		{
			"-alphaMinor", // Pre-release tags allowed only if all elements - x.y.z specified.
			args{"8.4", "8.4-alpha"},
			"",
			true,
		},
		{
			"-thresholdEmpty", // Pre-release tags allowed only if all elements - x.y.z specified.
			args{" ", "8.4"},
			"",
			true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got, err := getReplicationQuery(tt.args.threshold, tt.args.vers)
			if tt.wantErr != (err != nil) {
				t.Fatalf("getReplicationQuery() error = %v, wantErr %v", err, tt.wantErr)
			}

			if diff := cmp.Diff(tt.want, got); diff != "" {
				t.Errorf("getReplicationQuery(): %s", diff)
			}
		})
	}
}

func Test_getMasterHost(t *testing.T) {
	t.Parallel()

	type args = struct {
		data []map[string]string
	}

	tests := []struct {
		name string
		args args
		want []map[string]string
	}{
		{
			"+sourceHost",
			args{
				[]map[string]string{
					{"Source_Host": "123", "Source_User": "user"},
				},
			},
			[]map[string]string{
				{"Master_Host": "123"},
			},
		},
		{
			"+masterHost",
			args{
				[]map[string]string{
					{"Master_Host": "123", "Source_User": "user"},
				},
			},
			[]map[string]string{
				{"Master_Host": "123"},
			},
		},
		{
			"+notFound",
			args{
				[]map[string]string{
					{"Fake_Host": "123", "Source_User": "user"},
				},
			},
			[]map[string]string{},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got := extractMasterHost(tt.args.data)

			if diff := cmp.Diff(tt.want, got); diff != "" {
				t.Errorf("extractMasterHost(): %s", diff)
			}
		})
	}
}
