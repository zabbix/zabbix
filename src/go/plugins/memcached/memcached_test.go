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

package memcached

import (
	"io"
	"reflect"
	"testing"
	"time"

	"zabbix.com/pkg/plugin"
	"zabbix.com/plugins/memcached/mockserver"
)

func handleStat(req *mockserver.MCRequest, w io.Writer) (ret *mockserver.MCResponse) {
	ret = &mockserver.MCResponse{}
	ret.Status = mockserver.SUCCESS

	switch string(req.Key) {
	case statsTypeGeneral:
		ret.Key = []byte("version")
		ret.Body = []byte("1.4.15")
		_, _ = ret.Transmit(w)
	case statsTypeItems:
		ret.Key = []byte("items:1:number")
		ret.Body = []byte("1")
		_, _ = ret.Transmit(w)
	case statsTypeSlabs:
		ret.Key = []byte("1:chunk_size")
		ret.Body = []byte("96")
		_, _ = ret.Transmit(w)
		ret.Key = []byte("1:total_pages")
		ret.Body = []byte("1")
		_, _ = ret.Transmit(w)
	case statsTypeSizes:
		ret.Key = []byte("96")
		ret.Body = []byte("1")
		_, _ = ret.Transmit(w)
	case statsTypeSettings:
		ret.Key = []byte("maxconns")
		ret.Body = []byte("1024")
		_, _ = ret.Transmit(w)
	default:
		ret.Status = mockserver.UNKNOWN_COMMAND
		return
	}

	ret.Key = nil
	ret.Body = nil

	return ret
}

func handleNoOp(_ *mockserver.MCRequest, _ io.Writer) *mockserver.MCResponse {
	return &mockserver.MCResponse{}
}

func TestPlugin_Export(t *testing.T) {
	ms, err := mockserver.NewMockServer()
	if err != nil {
		panic(err)
	}

	ms.RegisterHandler(mockserver.STAT, handleStat)
	ms.RegisterHandler(mockserver.NOOP, handleNoOp)

	go ms.ListenAndServe()

	time.Sleep(1 * time.Second)

	type args struct {
		key    string
		params []string
		ctx    plugin.ContextProvider
	}

	p := Plugin{}
	p.Configure(&plugin.GlobalOptions{Timeout: 30}, nil)

	p.Start()
	defer p.Stop()

	tests := []struct {
		name       string
		p          *Plugin
		args       args
		wantResult interface{}
		wantErr    error
	}{
		{
			name:       "Unknown metric",
			p:          &p,
			args:       args{"unknown.metric", nil, nil},
			wantResult: nil,
			wantErr:    errorUnsupportedMetric,
		},
		{
			name:       "Too many parameters",
			p:          &p,
			args:       args{keyPing, []string{"tcp://127.0.0.1", "", "", "excess_param"}, nil},
			wantResult: nil,
			wantErr:    errorTooManyParameters,
		},
		{
			name:       "Should fail if unknown session given",
			p:          &p,
			args:       args{"foo", []string{"fakeSession"}, nil},
			wantResult: nil,
			wantErr:    errorUnknownSession,
		},
		{
			name:       "statsHandler should create connection to server and get general stats",
			p:          &p,
			args:       args{keyStats, []string{"tcp://" + ms.GetAddr()}, nil},
			wantResult: `{"version":"1.4.15"}`,
			wantErr:    nil,
		},
		{
			name:       "params should be passed correctly",
			p:          &p,
			args:       args{keyStats, []string{"tcp://" + ms.GetAddr(), "", "", statsTypeItems}, nil},
			wantResult: `{"items:1:number":"1"}`,
			wantErr:    nil,
		},
		{
			name:       "statsHandler should fail if server is not working",
			p:          &p,
			args:       args{keyStats, []string{"tcp://127.0.0.1:1"}, nil},
			wantResult: nil,
			wantErr:    errorCannotFetchData,
		},
		{
			name:       "pingHandler should create connection to server and run NoOp command",
			p:          &p,
			args:       args{keyPing, []string{"tcp://" + ms.GetAddr()}, nil},
			wantResult: pingOk,
			wantErr:    nil,
		},
		{
			name:       "pingHandler should fail if server is not working",
			p:          &p,
			args:       args{keyPing, []string{"tcp://127.0.0.1:1"}, nil},
			wantResult: pingFailed,
			wantErr:    nil,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gotResult, err := tt.p.Export(tt.args.key, tt.args.params, tt.args.ctx)
			if err != tt.wantErr {
				t.Errorf("Plugin.Export() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(gotResult, tt.wantResult) {
				t.Errorf("Plugin.Export() = %v, want %v", gotResult, tt.wantResult)
			}
		})
	}
}

func TestPlugin_Start(t *testing.T) {
	p := Plugin{}

	t.Run("Connection manager must be initialized", func(t *testing.T) {
		p.Start()
		if p.connMgr == nil {
			t.Error("Connection manager is not initialized")
		}
	})
}

func TestPlugin_Stop(t *testing.T) {
	p := Plugin{}
	p.Start()
	t.Run("Connection manager must be deinitialized", func(t *testing.T) {
		p.Stop()
		if p.connMgr != nil {
			t.Error("Connection manager is not deinitialized")
		}
	})
}
