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
	"strings"
	"testing"

	"github.com/google/go-cmp/cmp"
	"github.com/mediocregopher/radix/v3"
	"golang.zabbix.com/agent2/plugins/redis/conn"
)

func TestConfigHandler(t *testing.T) {
	t.Parallel()

	stubConn := radix.Stub("", "", func(args []string) any {
		switch strings.ToLower(args[2]) {
		case "param1":
			return map[string]string{"param1": "foo"}
		case "*":
			return map[string]string{"param1": "foo", "param2": "bar"}
		case "unknownparam":
			return map[string]string{}
		default:
			return errors.New("cannot fetch data")
		}
	})

	t.Cleanup(func() {
		err := stubConn.Close()
		if err != nil {
			t.Fatal(err)
		}
	})

	redisConn := conn.NewRedisConn(stubConn)

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
			name:    "+defaultPattern",
			args:    args{redisClient: redisConn, params: map[string]string{"Pattern": "*"}},
			want:    `{"param1":"foo","param2":"bar"}`,
			wantErr: false,
		},
		{
			name:    "+specificParam",
			args:    args{redisClient: redisConn, params: map[string]string{"Pattern": "param1"}},
			want:    "foo",
			wantErr: false,
		},
		{
			name:    "-unknownParam",
			args:    args{redisClient: redisConn, params: map[string]string{"Pattern": "UnknownParam"}},
			want:    nil,
			wantErr: true,
		},
		{
			name:    "-fetchError",
			args:    args{redisClient: redisConn, params: map[string]string{"Pattern": "WantErr"}},
			want:    nil,
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got, err := ConfigHandler(tt.args.redisClient, tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Fatalf("ConfigHandler() error = %v, wantErr %v", err, tt.wantErr)
			}

			if diff := cmp.Diff(tt.want, got); diff != "" {
				t.Fatalf("ConfigHandler() = %s", diff)
			}
		})
	}
}
