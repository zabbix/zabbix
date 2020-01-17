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

package redis

import (
	"reflect"
	"testing"
)

func Test_createConnectionId(t *testing.T) {
	type args struct {
		uri URI
	}
	tests := []struct {
		name string
		args args
		want connId
	}{
		{
			"Should return valid connId 1",
			args{URI{scheme: "tcp", host: "192.168.1.1", port: "6380", password: "sEcReT"}},
			connId{3, 68, 240, 241, 187, 94, 84, 141, 103, 190, 24, 149, 83, 19, 10, 200, 212, 8, 201, 145, 74, 11, 11, 16, 210,
				62, 232, 7, 140, 156, 64, 237, 163, 130, 201, 131, 79, 30, 40, 143, 218, 122, 213, 117, 188, 238, 90, 39, 34, 245, 73, 30, 50, 188,
				32, 203, 13, 0, 201, 207, 120, 104, 181, 172},
		},
		{
			"Should return valid connId 2",
			args{URI{scheme: "tcp", host: "127.0.0.1", port: "6379"}},
			connId{233, 246, 211, 207, 181, 200, 57, 1, 192, 18, 240, 163, 63, 83, 118, 35, 19, 80, 248, 119, 68, 46, 253, 161, 238, 224,
				13, 129, 221, 183, 172, 139, 158, 215, 39, 191, 101, 249, 195, 216, 101, 247, 21, 13, 139, 137, 138, 28, 206, 241, 215, 93, 195,
				246, 127, 247, 155, 196, 22, 2, 61, 191, 79, 137},
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := createConnectionId(tt.args.uri); !reflect.DeepEqual(got, tt.want) {
				t.Errorf("createConnectionId() = %v, want %v", got, tt.want)
			}
		})
	}
}
