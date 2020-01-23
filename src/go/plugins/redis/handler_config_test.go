/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
	"github.com/mediocregopher/radix/v3"
	"reflect"
	"strings"
	"testing"
	"zabbix.com/pkg/plugin"
)

func TestPlugin_configHandler(t *testing.T) {
	impl.Configure(&plugin.GlobalOptions{}, nil)

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

	conn := &redisConn{
		client: stubConn,
	}

	type args struct {
		conn   redisClient
		params []string
	}
	tests := []struct {
		name    string
		p       *Plugin
		args    args
		want    interface{}
		wantErr bool
	}{
		{
			"Pattern * should be used if it is not explicitly specified",
			&impl,
			args{conn: conn, params: []string{""}},
			`{"param1":"foo","param2":"bar"}`,
			false,
		},
		{
			"Should fetch specified parameter and return its value",
			&impl,
			args{conn: conn, params: []string{"", "param1"}},
			`foo`,
			false,
		},
		{
			"Should fail if parameter not found",
			&impl,
			args{conn: conn, params: []string{"", "UnknownParam"}},
			nil,
			true,
		},
		{
			"Should fail if error occurred",
			&impl,
			args{conn: conn, params: []string{"", "WantErr"}},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := tt.p.configHandler(tt.args.conn, tt.args.params)
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
