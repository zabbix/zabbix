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

package smart

import (
	"testing"
)

func Test_evaluateVersion(t *testing.T) {
	type args struct {
		versionDigits []int
	}
	tests := []struct {
		name    string
		args    args
		wantErr bool
	}{
		{"+correct_version", args{[]int{7, 1}}, false},
		{"+correct_version_one_digit", args{[]int{8}}, false},
		{"+correct_version_multiple_digits", args{[]int{7, 1, 2}}, false},
		{"-incorrect_version", args{[]int{7, 0}}, true},
		{"-malformed_version", args{[]int{-7, 0}}, true},
		{"-empty", args{}, true},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if err := evaluateVersion(tt.args.versionDigits); (err != nil) != tt.wantErr {
				t.Errorf("evaluateVersion() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}

func Test_cutPrefix(t *testing.T) {
	type args struct {
		in string
	}
	tests := []struct {
		name string
		args args
		want string
	}{
		{"+has_prefix", args{"/dev/sda"}, "sda"},
		{"-no_prefix", args{"sda"}, "sda"},
		{"-empty", args{""}, ""},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := cutPrefix(tt.args.in); got != tt.want {
				t.Errorf("cutPrefix() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_deviceParser_checkErr(t *testing.T) {
	type fields struct{ Smartctl smartctlField }
	tests := []struct {
		name    string
		fields  fields
		wantErr bool
		wantMsg string
	}{
		{"+no_err", fields{smartctlField{Messages: nil, ExitStatus: 0}}, false, ""},
		{"+warning", fields{smartctlField{Messages: []message{{"barfoo"}}, ExitStatus: 1}}, false, ""},
		{"-one_err", fields{smartctlField{Messages: []message{{"foobar"}}, ExitStatus: 2}}, true, "foobar"},
		{"-two_err", fields{smartctlField{Messages: []message{{"foobar"}, {"barfoo"}}, ExitStatus: 2}}, true, "foobar, barfoo"},
		{"-unknown_err/no message", fields{smartctlField{Messages: []message{}, ExitStatus: 2}}, true, "unknown error from smartctl"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			dp := deviceParser{Smartctl: tt.fields.Smartctl}
			err := dp.checkErr()
			if (err != nil) != tt.wantErr {
				t.Errorf("deviceParser.checkErr() error = %v, wantErr %v", err, tt.wantErr)
			}

			if (err != nil) && err.Error() != tt.wantMsg {
				t.Errorf("deviceParser.checkErr() error message = %v, want %v", err, tt.wantErr)
			}
		})
	}
}
