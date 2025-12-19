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
	"testing"

	"github.com/google/go-cmp/cmp"
	"github.com/mediocregopher/radix/v3"
	"golang.zabbix.com/agent2/plugins/redis/conn"
	"golang.zabbix.com/sdk/plugin/comms"
)

func TestPingHandler(t *testing.T) {
	t.Parallel()

	stubConn := radix.Stub("", "", func(args []string) any {
		return "PONG"
	})

	t.Cleanup(func() {
		err := stubConn.Close()
		if err != nil {
			t.Errorf("failed to close stub connection: %v", err)
		}
	})

	connection := conn.NewRedisConn(stubConn)

	brokenStubConn := radix.Stub("", "", func(args []string) any {
		return ""
	})

	t.Cleanup(func() {
		err := brokenStubConn.Close()
		if err != nil {
			t.Errorf("failed to close stub connection: %v", err)
		}
	})

	brokenConn := conn.NewRedisConn(brokenStubConn)

	closedStubConn := radix.Stub("", "", func(args []string) any {
		return ""
	})

	t.Cleanup(func() {
		err := closedStubConn.Close()
		if err != nil {
			t.Errorf("failed to close stub connection: %v", err)
		}
	})

	closedConn := conn.NewRedisConn(closedStubConn)

	type args struct {
		redisClient conn.RedisClient
		params      map[string]string
	}

	tests := []struct {
		name    string
		args    args
		want    any
		wantErr bool
	}{
		{
			name:    "+connectionOk",
			args:    args{redisClient: connection, params: nil},
			want:    comms.PingOk,
			wantErr: false,
		},
		{
			name:    "+wrongPingAnswer",
			args:    args{redisClient: brokenConn, params: nil},
			want:    comms.PingFailed,
			wantErr: false,
		},
		{
			name:    "+connectionFailed",
			args:    args{redisClient: closedConn, params: nil},
			want:    comms.PingFailed,
			wantErr: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got, err := PingHandler(tt.args.redisClient, tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Fatalf("PingHandler() error = %v, wantErr %v", err, tt.wantErr)
			}

			if diff := cmp.Diff(tt.want, got); diff != "" {
				t.Fatalf("PingHandler() = %s", diff)
			}
		})
	}
}
