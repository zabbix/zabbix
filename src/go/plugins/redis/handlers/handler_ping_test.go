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
	"fmt"
	"reflect"
	"testing"

	"github.com/mediocregopher/radix/v3"
	"golang.zabbix.com/agent2/plugins/redis/conn"
	"golang.zabbix.com/sdk/plugin/comms"
)

func TestPingHandler(t *testing.T) {
	stubConn := radix.Stub("", "", func(args []string) any {
		return "PONG"
	})
	defer stubConn.Close()

	connection := conn.NewRedisConn(stubConn)

	brokenStubConn := radix.Stub("", "", func(args []string) any {
		return ""
	})
	defer brokenStubConn.Close()

	brokenConn := conn.NewRedisConn(brokenStubConn)

	closedStubConn := radix.Stub("", "", func(args []string) any {
		return ""
	})
	closedStubConn.Close()

	closedConn := conn.NewRedisConn(closedStubConn)

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
			fmt.Sprintf("PingHandler should return %d if connection is ok", comms.PingOk),
			args{conn: connection},
			comms.PingOk,
			false,
		},
		{
			fmt.Sprintf("PingHandler should return %d if PING answers wrong", comms.PingFailed),
			args{conn: brokenConn},
			comms.PingFailed,
			false,
		},
		{
			fmt.Sprintf("PingHandler should return %d if connection failed", comms.PingFailed),
			args{conn: closedConn},
			comms.PingFailed,
			false,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := PingHandler(tt.args.conn, tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Errorf("Plugin.PingHandler() error = %v, wantErr %v", err, tt.wantErr)

				return
			}

			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("Plugin.PingHandler() = %v, want %v", got, tt.want)
			}
		})
	}
}
