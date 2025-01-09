//go:build linux
// +build linux

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

package proc

import (
	"regexp"
	"testing"
)

func Test_checkProccom(t *testing.T) {
	type args struct {
		cmd     string
		cmdline string
	}
	tests := []struct {
		name string
		args args
		want bool
	}{
		{"+base", args{"/foo/bar/foobar --start", "--start"}, true},
		{"+empty_cmdline", args{"/foo/bar/foobar --start", ""}, true},
		{"+complex_regex", args{"/foo/bar/foobar --start", "/.*/.*/.* --start"}, true},
		{"+no_match", args{"/foo/bar/foobar --start", "--stop"}, false},
		{"-fail_regex_compilation", args{"/foo/bar/foobar --start", "("}, true},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			cmdRgx, err := regexp.Compile(tt.args.cmdline)
			if err != nil {
				cmdRgx = nil
			}
			got := checkProccom(tt.args.cmd, cmdRgx)
			if got != tt.want {
				t.Errorf("checkProccom() = %v, want %v", got, tt.want)
			}
		})
	}
}
