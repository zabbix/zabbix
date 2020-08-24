/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

package tcpudp

import (
	"testing"
)

func Test_loginReceived(t *testing.T) {
	type args struct {
		buf []byte
	}
	tests := []struct {
		name string
		args args
		want bool
	}{
		{"+basic", args{[]byte("foobar:            ")}, true},
		{"+no_trailing_space", args{[]byte("foobar:")}, true},
		{"+space_in_string", args{[]byte("foo     bar:               ")}, true},
		{"-not_login", args{[]byte("foobar")}, false},
		{"-empty", args{[]byte("")}, false},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := loginReceived(tt.args.buf); got != tt.want {
				t.Errorf("loginReceived() = %v, want %v", got, tt.want)
			}
		})
	}
}
