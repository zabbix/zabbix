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
		vers string
	}

	tests := []struct {
		name string
		args args
		want replicQuery
	}{
		{
			"+equal",
			args{versionThreshold},
			replicaQueryNew,
		},
		{
			"+higher",
			args{"8.5"},
			replicaQueryNew,
		},
		{
			"+lower",
			args{"8.3"},
			replicaQueryOld,
		},
		{
			"+emptyLower",
			args{""},
			replicaQueryOld,
		},
		{
			"+totalRubbishLower",
			args{"rubbish"},
			replicaQueryOld,
		},
		{
			"+withPrefixLower",
			args{"v8.5"},
			replicaQueryOld,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got := getReplicationQuery(tt.args.vers)

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
