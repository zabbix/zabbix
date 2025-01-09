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
	"reflect"
	"testing"

	"golang.zabbix.com/sdk/plugin"
)

func TestPlugin_Start(t *testing.T) {
	t.Run("Connection manager must be initialized", func(t *testing.T) {
		impl.Start()
		if impl.connMgr == nil {
			t.Error("Connection manager is not initialized")
		}
	})
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
			name:       "Too many parameters",
			p:          &impl,
			args:       args{keyPing, []string{"localhost", "sEcReT", "param1", "param2"}, nil},
			wantResult: nil,
			wantErr:    true,
		},
		{
			name:       "Must fail if server is not working",
			p:          &impl,
			args:       args{keySlowlog, []string{"tcp://127.0.0.1:1"}, nil},
			wantResult: nil,
			wantErr:    true,
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

func TestPlugin_Stop(t *testing.T) {
	t.Run("Connection manager must be deinitialized", func(t *testing.T) {
		impl.Stop()
		if impl.connMgr != nil {
			t.Error("Connection manager is not deinitialized")
		}
	})
}
