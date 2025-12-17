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
	"testing"

	"github.com/google/go-cmp/cmp"
	"golang.zabbix.com/sdk/plugin"
)

//nolint:tparallel // due to plugin being a global variable, cannot test in parallel.
func TestPlugin_Start(t *testing.T) {
	t.Run("Connection manager must be initialized", func(t *testing.T) {
		t.Parallel()

		impl.Start()

		if impl.connMgr == nil {
			t.Error("Connection manager is not initialized")
		}
	})
}

//nolint:tparallel,paralleltest // due to plugin being a global variable, cannot test in parallel.
func TestPlugin_Export(t *testing.T) {
	type args struct {
		key       string
		rawParams []string
		ctx       plugin.ContextProvider
	}

	tests := []struct {
		name    string
		p       *Plugin
		args    args
		want    any
		wantErr bool
	}{
		{
			name:    "-tooManyParameters",
			p:       &impl,
			args:    args{keyPing, []string{"localhost", "sEcReT", "param1", "param2"}, nil},
			want:    nil,
			wantErr: true,
		},
		{
			name:    "-noServer",
			p:       &impl,
			args:    args{keySlowlog, []string{"tcp://127.0.0.1:1"}, nil},
			want:    nil,
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got, err := tt.p.Export(tt.args.key, tt.args.rawParams, tt.args.ctx)
			if (err != nil) != tt.wantErr {
				t.Fatalf("Plugin.Export() error = %v, wantErr %v", err, tt.wantErr)
			}

			diff := cmp.Diff(tt.want, got)
			if diff != "" {
				t.Fatalf("Plugin.Export() = %s", diff)
			}
		})
	}
}

//nolint:tparallel // due to plugin being a global variable, cannot test in parallel.
func TestPlugin_Stop(t *testing.T) {
	t.Run("Connection manager must be deinitialized", func(t *testing.T) {
		t.Parallel()

		impl.Stop()

		if impl.connMgr != nil {
			t.Error("Connection manager is not deinitialized")
		}
	})
}
