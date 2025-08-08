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
			"Pattern * should be used if it is not explicitly specified",
			args{conn: connection, params: map[string]string{"Pattern": "*"}},
			`{"param1":"foo","param2":"bar"}`,
			false,
		},
		{
			"Should fetch specified parameter and return its value",
			args{conn: connection, params: map[string]string{"Pattern": "param1"}},
			`foo`,
			false,
		},
		{
			"Should fail if parameter not found",
			args{conn: connection, params: map[string]string{"Pattern": "UnknownParam"}},
			nil,
			true,
		},
		{
			"Should fail if error occurred",
			args{conn: connection, params: map[string]string{"Pattern": "WantErr"}},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got, err := ConfigHandler(tt.args.conn, tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Errorf("Plugin.ConfigHandler() error = %v, wantErr %v", err, tt.wantErr)

				return
			}

			diff := cmp.Diff(tt.want, got)
			if diff != "" {
				t.Fatalf("Plugin.ConfigHandler() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}
