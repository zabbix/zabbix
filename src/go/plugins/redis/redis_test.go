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
	"reflect"
	"testing"
	"zabbix.com/pkg/plugin"
)

func TestRedis(t *testing.T) {
	return
}

func TestPlugin_Export(t *testing.T) {
	type args struct {
		key    string
		params []string
		ctx    plugin.ContextProvider
	}

	impl.Configure(&plugin.GlobalOptions{}, nil)

	tests := []struct {
		name       string
		p          *Plugin
		args       args
		wantResult interface{}
		wantErr    bool
	}{
		{
			"Unknown metric",
			&impl,
			args{"unknown.metric", nil, nil},
			nil,
			true,
		},
		{
			"Too many parameters",
			&impl,
			args{keyPing, []string{"param1", "param2"}, nil},
			nil,
			true,
		},
		{
			"Should fail if unknown session given",
			&impl,
			args{keyPing, []string{"fakeSession"}, nil},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gotResult, err := tt.p.Export(tt.args.key, tt.args.params, tt.args.ctx)
			if (err != nil) != tt.wantErr {
				t.Errorf("Plugin.Export() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(gotResult, tt.wantResult) {
				t.Errorf("Plugin.Export() = %v, want %v", gotResult, tt.wantResult)
			}
		})
	}
}
