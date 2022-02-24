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

package redis

import (
	"errors"
	"reflect"
	"strings"
	"testing"

	"github.com/mediocregopher/radix/v3"
)

func TestPlugin_configHandler(t *testing.T) {
	stubConn := radix.Stub("", "", func(args []string) interface{} {
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

	defer stubConn.Close()

	conn := &RedisConn{
		client: stubConn,
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
			"Pattern * should be used if it is not explicitly specified",
			args{conn: conn, params: map[string]string{"Pattern": "*"}},
			`{"param1":"foo","param2":"bar"}`,
			false,
		},
		{
			"Should fetch specified parameter and return its value",
			args{conn: conn, params: map[string]string{"Pattern": "param1"}},
			`foo`,
			false,
		},
		{
			"Should fail if parameter not found",
			args{conn: conn, params: map[string]string{"Pattern": "UnknownParam"}},
			nil,
			true,
		},
		{
			"Should fail if error occurred",
			args{conn: conn, params: map[string]string{"Pattern": "WantErr"}},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := configHandler(tt.args.conn, tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Errorf("Plugin.configHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("Plugin.configHandler() = %v, want %v", got, tt.want)
			}
		})
	}
}
