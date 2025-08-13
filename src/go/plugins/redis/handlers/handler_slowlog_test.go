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

package handlers

import (
	"errors"
	"testing"

	"github.com/google/go-cmp/cmp"
	"github.com/mediocregopher/radix/v3"
	"golang.zabbix.com/agent2/plugins/redis/conn"
)

func Test_GetLastSlowlogID(t *testing.T) {
	t.Parallel()

	type args struct {
		slowlog slowlog
	}

	tests := []struct {
		name    string
		args    args
		want    int64
		wantErr bool
	}{
		{
			name:    "+emptyLog",
			args:    args{slowlog: slowlog{}},
			want:    0,
			wantErr: false,
		},
		{
			name: "+lastId127",
			args: args{slowlog: slowlog{
				logItem{
					int64(127),
					int64(1571840072),
					3,
					[]any{},
				}}},
			want:    128,
			wantErr: false,
		},
		{
			name:    "-wrongItemType",
			args:    args{slowlog: slowlog{"wrong_item_type"}},
			want:    0,
			wantErr: true,
		},
		{
			name:    "-emptyLogItem",
			args:    args{slowlog: slowlog{logItem{}}},
			want:    0,
			wantErr: true,
		},
		{
			name:    "-wrongIdType",
			args:    args{slowlog: slowlog{logItem{"wrong_id_type"}}},
			want:    0,
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got, err := getLastSlowlogID(tt.args.slowlog)
			if (err != nil) != tt.wantErr {
				t.Errorf("getLastSlowlogID() error = %v, wantErr %v", err, tt.wantErr)

				return
			}

			if got != tt.want {
				t.Errorf("getLastSlowlogID() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_SlowlogHandler(t *testing.T) {
	t.Parallel()

	stubConn := radix.Stub("", "", func(args []string) any {
		return errors.New("cannot fetch data")
	})

	t.Cleanup(func() {
		err := stubConn.Close()
		if err != nil {
			t.Errorf("failed to close stub connection: %v", err)
		}
	})

	connection := conn.NewRedisConn(stubConn)

	type args struct {
		conn   conn.RedisClient
		params map[string]string
	}

	tests := []struct {
		name    string
		args    args
		want    any
		wantErr bool
	}{
		{
			name:    "-fetchError",
			args:    args{conn: connection, params: map[string]string{}},
			want:    nil,
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got, err := SlowlogHandler(tt.args.conn, tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Errorf("Plugin.SlowlogHandler() error = %v, wantErr %v", err, tt.wantErr)

				return
			}

			diff := cmp.Diff(tt.want, got)
			if diff != "" {
				t.Fatalf("Plugin.SlowlogHandler() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}
