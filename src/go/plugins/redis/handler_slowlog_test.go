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
	"testing"
	"zabbix.com/pkg/plugin"
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
					[]interface{}{},
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
			got, err := getLastSlowlogId(tt.args.slowlog)
			if (err != nil) != tt.wantErr {
				t.Errorf("getLastSlowlogId() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if got != tt.want {
				t.Errorf("getLastSlowlogId() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestPlugin_slowlogHandler(t *testing.T) {
	impl.Configure(&plugin.GlobalOptions{}, nil)

	stubConn := radix.Stub("", "", func(args []string) interface{} {
		return errors.New("cannot fetch data")
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
			"Should fail if error occurred",
			&impl,
			args{conn: conn, params: []string{"", "WantErr"}},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := tt.p.slowlogHandler(tt.args.conn, tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Errorf("Plugin.slowlogHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("Plugin.slowlogHandler() = %v, want %v", got, tt.want)
			}
		})
	}
}
