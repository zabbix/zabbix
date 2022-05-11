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

package memcached

import (
	"errors"
	"fmt"
	"reflect"
	"testing"
)

func TestPlugin_pingHandler(t *testing.T) {
	aliveConn := stubConn{
		StatsFunc: nil,
		NoOpFunc: func() error {
			return nil
		},
	}

	badConn := stubConn{
		StatsFunc: nil,
		NoOpFunc: func() error {
			return errors.New("some error")
		},
	}

	type args struct {
		conn   MCClient
		params map[string]string
	}

	tests := []struct {
		name    string
		args    args
		want    interface{}
		wantErr error
	}{
		{
			name: fmt.Sprintf("pingHandler should return %d if connection is ok", pingOk),
			args: args{
				conn:   &badConn,
				params: map[string]string{},
			},
			want:    pingFailed,
			wantErr: nil,
		},
		{
			name: fmt.Sprintf("pingHandler should return %d if request failed", pingFailed),
			args: args{
				conn:   &aliveConn,
				params: map[string]string{},
			},
			want:    pingOk,
			wantErr: nil,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := pingHandler(tt.args.conn, tt.args.params)
			if !errors.Is(err, tt.wantErr) {
				t.Errorf("Plugin.pingHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("Plugin.pingHandler() = %v, want %v", got, tt.want)
			}
		})
	}
}
