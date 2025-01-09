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
