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

package ceph

import (
	"fmt"
	"reflect"
	"testing"
)

func Test_pingHandler(t *testing.T) {
	type args struct {
		data map[command][]byte
	}
	tests := []struct {
		name    string
		args    args
		want    interface{}
		wantErr bool
	}{
		{
			fmt.Sprintf("Must return %d if connection is ok", pingOk),
			args{map[command][]byte{cmdHealth: fixtures[cmdHealth]}},
			pingOk,
			false,
		},
		{
			fmt.Sprintf("Must return %d if connection failed", pingFailed),
			args{map[command][]byte{cmdHealth: fixtures[cmdBroken]}},
			pingFailed,
			false,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := pingHandler(tt.args.data)
			if (err != nil) != tt.wantErr {
				t.Errorf("pingHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("pingHandler() got = %v, want %v", got, tt.want)
			}
		})
	}
}
