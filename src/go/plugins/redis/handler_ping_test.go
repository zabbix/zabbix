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

package redis

import (
	"fmt"
	"reflect"
	"testing"

	"github.com/mediocregopher/radix/v3"
)

func TestPlugin_pingHandler(t *testing.T) {
	stubConn := radix.Stub("", "", func(args []string) interface{} {
		return "PONG"
	})
	defer stubConn.Close()

	conn := &RedisConn{
		client: stubConn,
	}

	brokenStubConn := radix.Stub("", "", func(args []string) interface{} {
		return ""
	})
	defer brokenStubConn.Close()

	brokenConn := &RedisConn{
		client: brokenStubConn,
	}

	closedStubConn := radix.Stub("", "", func(args []string) interface{} {
		return ""
	})
	closedStubConn.Close()

	closedConn := &RedisConn{
		client: closedStubConn,
	}

	type args struct {
		conn   redisClient
		params map[string]string
	}
	tests := []struct {
		name    string
		args    args
		want    interface{}
		wantErr bool
	}{
		{
			fmt.Sprintf("pingHandler should return %d if connection is ok", pingOk),
			args{conn: conn},
			pingOk,
			false,
		},
		{
			fmt.Sprintf("pingHandler should return %d if PING answers wrong", pingFailed),
			args{conn: brokenConn},
			pingFailed,
			false,
		},
		{
			fmt.Sprintf("pingHandler should return %d if connection failed", pingFailed),
			args{conn: closedConn},
			pingFailed,
			false,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := pingHandler(tt.args.conn, tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Errorf("Plugin.pingHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("Plugin.pingHandler() = %v, want %v", got, tt.want)
			}
		})
	}
}
