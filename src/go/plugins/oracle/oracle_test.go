//go:build oracle_tests
// +build oracle_tests

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

package oracle

import (
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"reflect"
	"runtime"
	"strings"
	"testing"

	"github.com/omeid/go-yarn"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

type TestConfig struct {
	ora_uri  string
	ora_user string
	ora_pwd  string
	ora_srv  string
}

var Config TestConfig

func TestMain(m *testing.M) {
	Config = TestConfig{
		ora_uri:  os.Getenv("ORA_URI"),
		ora_user: os.Getenv("ORA_USER"),
		ora_pwd:  os.Getenv("ORA_PWD"),
		ora_srv:  os.Getenv("ORA_SRV"),
	}

	if Config.ora_uri == "" || Config.ora_user == "" || Config.ora_pwd == "" || Config.ora_srv == "" {
		fmt.Println("Environment variables ORA_URI, ORA_USER, ORA_PWD and ORA_SRV must be set to run tests")
		os.Exit(-1)
	}

	impl.Init(pluginName)
	impl.Configure(&plugin.GlobalOptions{Timeout: 30}, nil)

	os.Exit(m.Run())
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
	type args struct {
		key    string
		params []string
		ctx    plugin.ContextProvider
	}

	impl.connMgr.queryStorage = yarn.NewFromMap(map[string]string{
		"TestQuery.sql": "SELECT :1 AS res FROM DUAL",
	})

	tests := []struct {
		name       string
		p          *Plugin
		args       args
		wantResult interface{}
		wantErr    error
	}{
		{
			name:       "Too many parameters",
			p:          &impl,
			args:       args{keyPing, []string{Config.ora_uri, Config.ora_user, Config.ora_pwd, Config.ora_srv, "excess_param"}, nil},
			wantResult: nil,
			wantErr:    zbxerr.ErrorTooManyParameters,
		},
		{
			name:       "Should fail if unknown session given",
			p:          &impl,
			args:       args{keyUser, []string{"fakeSession"}, nil},
			wantResult: nil,
			wantErr:    errors.New("Connection failed: ORA-12545: Connect failed because target host or object does not exist."),
		},
		{
			name:       "pingHandler should return pingOk if connection is alive",
			p:          &impl,
			args:       args{keyPing, []string{Config.ora_uri, Config.ora_user, Config.ora_pwd, Config.ora_srv}, nil},
			wantResult: pingOk,
			wantErr:    nil,
		},
		{
			name:       "pingHandler should fail if server is not working",
			p:          &impl,
			args:       args{keyPing, []string{"tcp://127.0.0.1:1"}, nil},
			wantResult: pingFailed,
			wantErr:    nil,
		},
		{
			name:       "customQueryHandler should echo its parameter",
			p:          &impl,
			args:       args{keyCustomQuery, []string{Config.ora_uri, Config.ora_user, Config.ora_pwd, Config.ora_srv, "TestQuery", "OK"}, nil},
			wantResult: "[{\"RES\":\"OK\"}]",
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

func getHandlerName(metric string) string {
	function := runtime.FuncForPC(reflect.ValueOf(getHandlerFunc(metric)).Pointer()).Name()
	parts := strings.Split(function, ".")
	return parts[len(parts)-1]
}

func TestHandlers(t *testing.T) {
	for metric, _ := range plugin.Metrics {
		if metric == keyPing || metric == keyCustomQuery {
			continue
		}

		got, err := impl.Export(metric, []string{Config.ora_uri, Config.ora_user, Config.ora_pwd, Config.ora_srv}, nil)
		if err != nil {
			t.Errorf("Plugin.%s() failed with error: %s", getHandlerName(metric), err.Error())
			continue
		}

		var obj interface{}

		if err := json.Unmarshal([]byte(got.(string)), &obj); err != nil {
			t.Errorf("Plugin.%s() = %+v, expected valid JSON.", getHandlerName(metric), got)
			continue
		}
	}

	// Test for ErrorCannotFetchData
	for _, conn := range impl.connMgr.connections {
		conn.client.Close()
	}

	for metric, _ := range plugin.Metrics {
		if metric == keyPing || metric == keyCustomQuery {
			continue
		}

		_, err := impl.Export(metric, []string{Config.ora_uri, Config.ora_user, Config.ora_pwd, Config.ora_srv}, nil)
		if errors.Unwrap(err) != zbxerr.ErrorCannotFetchData {
			t.Errorf("Plugin.%s() should return %q if server is not working, got: %q",
				getHandlerName(metric), zbxerr.ErrorCannotFetchData, errors.Unwrap(err))
			continue
		}

	}

	// Test for ErrorTooManyParameters
	for metric, _ := range plugin.Metrics {
		if metric == keyCustomQuery {
			continue
		}

		_, err := impl.Export(metric, []string{Config.ora_uri, Config.ora_user, Config.ora_pwd, Config.ora_srv, "excess_param", "excess_param", "excess_param"}, nil)
		if err != zbxerr.ErrorTooManyParameters {
			t.Errorf("Plugin.%s() should fail if too many parameters passed", getHandlerName(metric))
			continue
		}
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
