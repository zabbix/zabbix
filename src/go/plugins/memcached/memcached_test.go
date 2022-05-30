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

package memcached

import (
	"errors"
	"io"
	"reflect"
	"testing"
	"time"

	"git.zabbix.com/ap/plugin-support/zbxerr"

	"git.zabbix.com/ap/plugin-support/plugin"
	"zabbix.com/plugins/memcached/mockserver"
)

func handleStat(req *mockserver.MCRequest, w io.Writer) (ret *mockserver.MCResponse) {
	ret = &mockserver.MCResponse{}
	ret.Status = mockserver.Success

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
		ret.Status = mockserver.UnknownCommand
		return
	}

	ret.Key = nil
	ret.Body = nil

	return ret
}

func handleNoOp(_ *mockserver.MCRequest, _ io.Writer) *mockserver.MCResponse {
	return &mockserver.MCResponse{}
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

func TestPlugin_Export(t *testing.T) {
	ms, err := mockserver.NewMockServer()
	if err != nil {
		panic(err)
	}

	ms.RegisterHandler(mockserver.Stat, handleStat)
	ms.RegisterHandler(mockserver.Noop, handleNoOp)

	go ms.ListenAndServe()

	time.Sleep(1 * time.Second)

	type args struct {
		key    string
		params []string
		ctx    plugin.ContextProvider
	}

	p := Plugin{}
	p.Init(pluginName)
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
			name:       "Too many parameters",
			p:          &p,
			args:       args{keyPing, []string{"tcp://127.0.0.1", "", "", "excess_param"}, nil},
			wantResult: nil,
			wantErr:    zbxerr.ErrorTooManyParameters,
		},
		{
			name:       "Must successfully get general stats",
			p:          &p,
			args:       args{keyStats, []string{"tcp://" + ms.GetAddr()}, nil},
			wantResult: `{"version":"1.4.15"}`,
			wantErr:    nil,
		},
		{
			name:       "Type parameter must be passed correctly",
			p:          &p,
			args:       args{keyStats, []string{"tcp://" + ms.GetAddr(), "", "", statsTypeItems}, nil},
			wantResult: `{"items:1:number":"1"}`,
			wantErr:    nil,
		},
		{
			name:       "Must fail if server is not working",
			p:          &p,
			args:       args{keyStats, []string{"tcp://127.0.0.1:1"}, nil},
			wantResult: nil,
			wantErr:    errors.New("Cannot fetch data: dial tcp 127.0.0.1:1: connect: connection refused."),
		},
		{
			name:       "Must not fail if server is not working for " + keyPing,
			p:          &p,
			args:       args{keyPing, []string{"tcp://127.0.0.1:1"}, nil},
			wantResult: pingFailed,
			wantErr:    nil,
		},
		{
			name:       "pingHandler must create connection to server and run NoOp command",
			p:          &p,
			args:       args{keyPing, []string{"tcp://" + ms.GetAddr()}, nil},
			wantResult: pingOk,
			wantErr:    nil,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gotResult, err := tt.p.Export(tt.args.key, tt.args.params, tt.args.ctx)
			if err != nil && (tt.wantErr == nil || err.Error() != tt.wantErr.Error()) {
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
	p := Plugin{}
	p.Start()
	t.Run("Connection manager must be deinitialized", func(t *testing.T) {
		p.Stop()
		if p.connMgr != nil {
			t.Error("Connection manager is not deinitialized")
		}
	})
}
