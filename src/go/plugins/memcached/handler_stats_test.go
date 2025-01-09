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

package memcached

import (
	"errors"
	"reflect"
	"testing"

	"github.com/memcachier/mc/v3"
	"golang.zabbix.com/sdk/zbxerr"
)

func TestPlugin_statsHandler(t *testing.T) {
	fakeConn := stubConn{
		StatsFunc: func(key string) (mc.McStats, error) {
			switch key {
			case statsTypeGeneral:
				return mc.McStats{
					"pid":     "1234",
					"version": "1.4.15",
				}, nil

			case statsTypeSizes:
				return mc.McStats{
					"96": "1",
				}, nil

			case statsTypeSettings: // generates error for tests
				return nil, errors.New("some error")

			default:
				return nil, errors.New("unknown type")
			}
		},
		NoOpFunc: nil,
	}

	type args struct {
		conn   MCClient
		params map[string]string
	}

	tests := []struct {
		name    string
		args    args
		want    interface{}
		wantErr error
	}{
		{
			name: "Should return error if cannot fetch data",
			args: args{
				conn:   &fakeConn,
				params: map[string]string{"Type": statsTypeSettings},
			},
			want:    nil,
			wantErr: zbxerr.ErrorCannotFetchData,
		},
		{
			name: "Type should be passed to stats command if specified",
			args: args{
				conn:   &fakeConn,
				params: map[string]string{"Type": statsTypeSizes},
			},
			want:    `{"96":"1"}`,
			wantErr: nil,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := statsHandler(tt.args.conn, tt.args.params)
			if !errors.Is(err, tt.wantErr) {
				t.Errorf("Plugin.statsHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}

			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("Plugin.statsHandler() = %v, want %v", got, tt.want)
			}
		})
	}
}
