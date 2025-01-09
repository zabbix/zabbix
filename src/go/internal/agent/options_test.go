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

package agent

import "testing"

func TestCutAfterN(t *testing.T) {
	type args struct {
		s string
		n int
	}
	tests := []struct {
		name string
		args args
		want string
	}{
		{"+base", args{"foobar", 3}, "foo"},
		{"+shorter string", args{"foo", 4}, "foo"},
		{"+cut after zero", args{"foobar", 0}, ""},
		{"+shorter by one byte", args{"foo", 2}, "fo"},
		{"-empty string", args{"", 3}, ""},
		{"-empty", args{"", 0}, ""},
		{"-one utf-8 character", args{"ыы", 1}, "ы"},
		{"-two utf-8 characters", args{"ыыыы", 2}, "ыы"},
		{"-japanese utf-8 characters", args{"日本語", 2}, "日本"},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := CutAfterN(tt.args.s, tt.args.n); got != tt.want {
				t.Errorf("CutAfterN() = %v, want %v", got, tt.want)
			}
		})
	}
}
