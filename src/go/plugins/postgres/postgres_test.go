//go:build postgres_tests
// +build postgres_tests

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

package postgres

import (
	"os"
	"reflect"
	"testing"

	"github.com/omeid/go-yarn"
	"zabbix.com/pkg/log"
	"zabbix.com/pkg/plugin"
)

var testParamDatabase = map[string]string{"Database": "postgres"}

// TestMain does the before and after setup
func TestMain(m *testing.M) {
	var code int

	_ = log.Open(log.Console, log.Debug, "", 0)

	log.Infof("[TestMain] Start connecting to PostgreSQL...")
	if err := createConnection(); err != nil {
		log.Infof("failed to create connection to PostgreSQL for tests")
		os.Exit(code)
	}
	// initialize plugin
	impl.Init(pluginName)
	impl.Configure(&plugin.GlobalOptions{Timeout: 30}, nil)

	code = m.Run()
	if code != 0 {
		log.Critf("failed to run PostgreSQL tests")
		os.Exit(code)
	}
	log.Infof("[TestMain] Cleaning up...")
	os.Exit(code)
}

func TestPlugin_Start(t *testing.T) {
	t.Run("Connection manager must be initialized", func(t *testing.T) {
		impl.Start()
		if impl.connMgr == nil {
			t.Error("Connection manager is not initialized")
		}
	})
}

func TestPlugin_Export(t *testing.T) {
	pgAddr, pgUser, pgPwd, pgDb := getEnv()

	type args struct {
		key    string
		params []string
		ctx    plugin.ContextProvider
	}

	//impl.Configure(&plugin.GlobalOptions{Timeout: 30}, nil)
	impl.connMgr.queryStorage = yarn.NewFromMap(map[string]string{
		"TestQuery.sql": "SELECT $1::text AS res",
	})

	tests := []struct {
		name       string
		p          *Plugin
		args       args
		wantResult interface{}
		wantErr    bool
	}{
		{
			"Check PG Ping",
			&impl,
			args{keyPing, []string{pgAddr, pgUser, pgPwd}, nil},
			pingOk,
			false,
		},
		{
			"Too many parameters",
			&impl,
			args{keyPing, []string{"param1", "param2", "param3", "param4", "param5"}, nil},
			nil,
			true,
		},
		{
			"Check wal handler",
			&impl,
			args{keyWal, []string{pgAddr, pgUser, pgPwd}, nil},
			nil,
			false,
		},
		{
			"Check custom queries handler. Should return 1 as text",
			&impl,
			args{keyCustomQuery, []string{pgAddr, pgUser, pgPwd, pgDb, "TestQuery", "echo"}, nil},
			"[{\"res\":\"echo\"}]",
			false,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gotResult, err := tt.p.Export(tt.args.key, tt.args.params, tt.args.ctx)
			if (err != nil) != tt.wantErr {
				t.Errorf("Plugin.Export() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(gotResult, tt.wantResult) && tt.args.key != keyWal {
				t.Errorf("Plugin.Export() = %v, want %v", gotResult, tt.wantResult)
			}
			if tt.args.key == keyWal && len(gotResult.(string)) == 0 {
				t.Errorf("Plugin.Export() result for keyPostgresWal length is 0")
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
