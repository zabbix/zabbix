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
	"reflect"
	"testing"

	"github.com/godror/godror"
)

func Test_getConnParams(t *testing.T) {
	type args struct {
		privilege string
	}
	tests := []struct {
		name    string
		args    args
		wantOut godror.ConnParams
		wantErr bool
	}{
		{"no privilege", args{}, godror.ConnParams{}, false},
		{"sysdba", args{"sysdba"}, godror.ConnParams{IsSysDBA: true}, false},
		{"sysoper", args{"sysoper"}, godror.ConnParams{IsSysOper: true}, false},
		{"sysasm", args{"sysasm"}, godror.ConnParams{IsSysASM: true}, false},
		{"empty_privilege", args{""}, godror.ConnParams{}, false},
		{"incorrect_privilege", args{"foobar"}, godror.ConnParams{}, true},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gotOut, err := getConnParams(tt.args.privilege)
			if (err != nil) != tt.wantErr {
				t.Errorf("getConnParams() error = %v, wantErr %v", err, tt.wantErr)

				return
			}
			if !reflect.DeepEqual(gotOut, tt.wantOut) {
				t.Errorf("getConnParams() = %v, want %v", gotOut, tt.wantOut)
			}
		})
	}
}

func Test_splitUserPrivilege(t *testing.T) {
	type args struct {
		params map[string]string
	}
	tests := []struct {
		name          string
		args          args
		wantUser      string
		wantPrivilege string
		wantErr       bool
	}{
		{"only_user", args{map[string]string{"User": "foobar"}}, "foobar", "", false},
		{"sysdba_privilege_lowercase", args{map[string]string{"User": "foobar as sysdba"}}, "foobar", "sysdba", false},
		{"sysdba_privilege_uppercase", args{map[string]string{"User": "foobar AS SYSDBA"}}, "foobar", "sysdba", false},
		{"sysdba_privilege_mix", args{map[string]string{"User": "foobar AS sySdBa"}}, "foobar", "sysdba", false},
		{"sysoper_privilege_lowercase", args{map[string]string{"User": "foobar as sysoper"}}, "foobar", "sysoper", false},
		{"sysoper_privilege_uppercase", args{map[string]string{"User": "foobar AS SYSOPER"}}, "foobar", "sysoper", false},
		{"sysoper_privilege_mix", args{map[string]string{"User": "foobar AS sysOpEr"}}, "foobar", "sysoper", false},
		{"sysasm_privilege_lowercase", args{map[string]string{"User": "foobar as sysasm"}}, "foobar", "sysasm", false},
		{"sysasm_privilege_uppercase", args{map[string]string{"User": "foobar AS SYSASM"}}, "foobar", "sysasm", false},
		{"sysasm_privilege_mix", args{map[string]string{"User": "foobar AS sysAsM"}}, "foobar", "sysasm", false},
		{"incorrect_privilege", args{map[string]string{"User": "foobar as barfoo"}}, "foobar as barfoo", "", false},
		{"empty_user", args{map[string]string{"User": ""}}, "", "", false},
		{"no_user", args{map[string]string{}}, "", "", true},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gotUser, gotPrivilege, err := splitUserPrivilege(tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Errorf("splitUserPrivilege() error = %v, wantErr %v", err, tt.wantErr)

				return
			}
			if gotUser != tt.wantUser {
				t.Errorf("splitUserPrivilege() gotUser = %v, want %v", gotUser, tt.wantUser)
			}
			if gotPrivilege != tt.wantPrivilege {
				t.Errorf("splitUserPrivilege() gotPrivilege = %v, want %v", gotPrivilege, tt.wantPrivilege)
			}
		})
	}
}
