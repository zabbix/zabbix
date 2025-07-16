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
	"reflect"
	"testing"

	"github.com/mediocregopher/radix/v3"
	"golang.zabbix.com/agent2/plugins/redis/conn"
)

func Test_getLastSlowlogId(t *testing.T) {
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
			"Should return 0 for empty log",
			args{slowlog{}},
			0,
			false,
		},
		{
			"Should return 128 for last ID=127",
			args{slowlog{
				logItem{
					int64(127),
					int64(1571840072),
					3,
					[]any{},
				}}},
			128,
			false,
		},
		{
			"Should fail if logItem is not slice",
			args{slowlog{"wrong_item_type"}},
			0,
			true,
		},
		{
			"Should fail if logItem is empty",
			args{slowlog{logItem{}}},
			0,
			true,
		},
		{
			"Should fail if logItem id is not int64",
			args{slowlog{logItem{"wrong_id_type"}}},
			0,
			true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
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

func TestPlugin_slowlogHandler(t *testing.T) {
	stubConn := radix.Stub("", "", func(args []string) any {
		return errors.New("cannot fetch data")
	})

	defer stubConn.Close()

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
			"Should fail if error occurred",
			args{conn: connection, params: map[string]string{}},
			nil,
			true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := SlowlogHandler(tt.args.conn, tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Errorf("Plugin.SlowlogHandler() error = %v, wantErr %v", err, tt.wantErr)

				return
			}

			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("Plugin.SlowlogHandler() = %v, want %v", got, tt.want)
			}
		})
	}
}
