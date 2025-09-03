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

package oracle

import (
	"testing"

	"github.com/godror/godror/dsn"
	"github.com/google/go-cmp/cmp"
)

// Test_splitUserAndPrivilege verifies the parsing of user strings with and without roles.
func Test_splitUserAndPrivilege(t *testing.T) {
	t.Parallel()

	type args struct {
		params map[string]string
	}

	tests := []struct {
		name          string
		args          args
		wantUser      string
		wantPrivilege dsn.AdminRole
		wantErr       bool
	}{
		{
			name:          "+simple username",
			args:          args{params: map[string]string{"User": "foobar"}},
			wantUser:      "foobar",
			wantPrivilege: dsn.NoRole,
			wantErr:       false,
		},
		{
			name:          "+SYSDBA privilege with mixed case",
			args:          args{params: map[string]string{"User": "foobar AS sySdBa"}},
			wantUser:      "foobar",
			wantPrivilege: dsn.SysDBA,
			wantErr:       false,
		},
		{
			name:          "+SYSOPER privilege with extra spaces",
			args:          args{params: map[string]string{"User": "foobar   as   sysoper"}},
			wantUser:      "foobar",
			wantPrivilege: dsn.SysOPER,
			wantErr:       false,
		},
		{
			name:          "+SYSASM privilege uppercase",
			args:          args{params: map[string]string{"User": "god AS SYSASM"}},
			wantUser:      "god",
			wantPrivilege: dsn.SysASM,
			wantErr:       false,
		},
		{
			name:          "+SYSBACKUP privilege",
			args:          args{params: map[string]string{"User": "backup_user as sysbackup"}},
			wantUser:      "backup_user",
			wantPrivilege: dsn.SysBACKUP,
			wantErr:       false,
		},
		{
			name:          "+SYSDG privilege for Data Guard",
			args:          args{params: map[string]string{"User": "dg_admin as sysdg"}},
			wantUser:      "dg_admin",
			wantPrivilege: dsn.SysDG,
			wantErr:       false,
		},
		{
			name:          "+SYSKM privilege for Keystore Management",
			args:          args{params: map[string]string{"User": "key_mgr AS SYSKM"}},
			wantUser:      "key_mgr",
			wantPrivilege: dsn.SysKM,
			wantErr:       false,
		},
		{
			name:          "+SYSRAC privilege for RAC Admin",
			args:          args{params: map[string]string{"User": "rac_user As SysRac"}},
			wantUser:      "rac_user",
			wantPrivilege: dsn.SysRAC,
			wantErr:       false,
		},
		{
			name:          "-missing user key in params",
			args:          args{params: map[string]string{}},
			wantUser:      "",
			wantPrivilege: dsn.NoRole,
			wantErr:       true,
		},
		{
			name:          "+user string is empty",
			args:          args{params: map[string]string{"User": ""}},
			wantUser:      "",
			wantPrivilege: dsn.NoRole,
			wantErr:       false,
		},
		{
			name:          "+unknown privilege",
			args:          args{params: map[string]string{"User": "foobar as barfoo"}},
			wantUser:      "foobar as barfoo",
			wantPrivilege: dsn.NoRole,
			wantErr:       false,
		},
		{
			name:          "-invalid format with too many parts",
			args:          args{params: map[string]string{"User": "foobar as sysdba extra"}},
			wantUser:      "",
			wantPrivilege: dsn.NoRole,
			wantErr:       true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			gotUser, gotPrivilege, err := splitUserAndPrivilege(tt.args.params)

			if (err != nil) != tt.wantErr {
				t.Fatalf("splitUserAndPrivilege() error = %v, wantErr %v", err, tt.wantErr)
			}

			if tt.wantErr {
				return
			}

			if diff := cmp.Diff(tt.wantUser, gotUser); diff != "" {
				t.Errorf("splitUserAndPrivilege() gotUser mismatch (-want +got):\n%s", diff)
			}

			if diff := cmp.Diff(tt.wantPrivilege, gotPrivilege); diff != "" {
				t.Errorf("splitUserAndPrivilege() gotPrivilege mismatch (-want +got):\n%s", diff)
			}
		})
	}
}
