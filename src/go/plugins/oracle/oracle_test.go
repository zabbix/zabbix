//go:build oracle_tests

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
	"regexp"
	"runtime"
	"strings"
	"testing"

	"github.com/omeid/go-yarn"
	"golang.zabbix.com/agent2/plugins/oracle/dbconn"
	"golang.zabbix.com/agent2/plugins/oracle/handlers"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

var (
	config    dbconn.TestConfig //nolint:gochecknoglobals
	OraVersRx = regexp.MustCompile(`^\d{2}\.\d+\.\d+\.\d+\.\d+$`)
)

func TestMain(m *testing.M) {
	config = dbconn.TestConfig{
		OraURI:  os.Getenv("ORA_URI"),
		OraUser: os.Getenv("ORA_USER"),
		OraPwd:  os.Getenv("ORA_PWD"),
		OraSrv:  os.Getenv("ORA_SRV"),
	}

	if config.OraURI == "" || config.OraUser == "" || config.OraPwd == "" || config.OraSrv == "" {
		fmt.Println( //nolint:forbidigo
			"Environment variables ORA_URI, ORA_USER, ORA_PWD and ORA_SRV must be set to run tests",
		)

		os.Exit(-1)
	}

	impl.Init(pluginName)
	impl.Configure(&plugin.GlobalOptions{Timeout: 30}, nil)

	os.Exit(m.Run())
}

func TestPlugin_Start(t *testing.T) { //nolint:paralleltest //nolint:paralleltest
	t.Run("Connection manager must be initialized", func(t *testing.T) {
		impl.Start()

		if impl.connMgr == nil {
			t.Error("Connection manager is not initialized")
		}
	})
}

func TestPlugin_Export(t *testing.T) { //nolint:paralleltest
	// When isolated test function is launched - impl.connMgr is nil.
	if impl.connMgr == nil {
		impl.Start()
		defer impl.Stop()
	}

	type args struct {
		key    string
		params []string
		ctx    plugin.ContextProvider
	}

	impl.connMgr.QueryStorage = yarn.NewFromMap(map[string]string{
		"TestQuery.sql": "SELECT :1 AS res FROM DUAL",
	})

	tests := []struct {
		name       string
		p          *Plugin
		args       args
		wantResult any
		wantErr    error
	}{
		{
			name:       "+pingHandlerShouldReturnPingOkIfConnectionAlive",
			p:          &impl,
			args:       args{keyPing, []string{config.OraURI, config.OraUser, config.OraPwd, config.OraSrv}, nil},
			wantResult: handlers.PingOk,
			wantErr:    nil,
		},
		{
			name:       "+pingHandlerShouldFailIfServerNotWorking",
			p:          &impl,
			args:       args{keyPing, []string{"tcp://127.0.0.1:1"}, nil},
			wantResult: handlers.PingFailed,
			wantErr:    nil,
		},
		{
			name: "-tooManyParameters",
			p:    &impl,
			args: args{
				keyPing,
				[]string{config.OraURI, config.OraUser, config.OraPwd, config.OraSrv, "excess_param"},
				nil,
			},
			wantResult: nil,
			wantErr:    zbxerr.ErrorTooManyParameters,
		},
		{
			name:       "-shouldFailIfUnknownSessionGiven",
			p:          &impl,
			args:       args{keyUser, []string{"fakeSession"}, nil},
			wantResult: nil,
			wantErr:    errors.New("ORA-12545: Connect failed because target host or object does not exist"),
		},
	}

	for _, tt := range tests { //nolint:paralleltest
		t.Run(tt.name, func(t *testing.T) {
			gotResult, err := tt.p.Export(tt.args.key, tt.args.params, tt.args.ctx)

			if err != nil && (tt.wantErr == nil || !strings.Contains(err.Error(), tt.wantErr.Error())) {
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
	function := runtime.FuncForPC(reflect.ValueOf(metricsMeta[metric]).Pointer()).Name()
	parts := strings.Split(function, ".")

	return parts[len(parts)-1]
}

func TestHandlerPing(t *testing.T) { //nolint:paralleltest
	// When isolated test function is launched - impl.connMgr is nil.
	if impl.connMgr == nil {
		impl.Start()
		defer impl.Stop()
	}

	metric := keyPing

	got, err := impl.Export(metric, []string{config.OraURI, config.OraUser, config.OraPwd, config.OraSrv}, nil)
	if err != nil {
		t.Fatalf("Plugin.%s() failed with error: %s", getHandlerName(metric), err.Error())
	}

	gotNum, ok := got.(int)
	if !ok {
		t.Fatalf("Plugin.%s() = %d, expected = integer", getHandlerName(metric), got)
	}

	if gotNum != handlers.PingOk {
		t.Fatalf("Plugin.%s() = %d, expected = %d", getHandlerName(metric), gotNum, handlers.PingOk)
	}
}

func TestHandlerVersion(t *testing.T) { //nolint:paralleltest
	// When isolated test function is launched - impl.connMgr is nil.
	if impl.connMgr == nil {
		impl.Start()
		defer impl.Stop()
	}

	metric := keyVersion

	got, err := impl.Export(metric, []string{config.OraURI, config.OraUser, config.OraPwd, config.OraSrv}, nil)
	if err != nil {
		t.Fatalf("Plugin.%s() failed with error: %s", getHandlerName(metric), err.Error())
	}

	gotVers, ok := got.(string)
	if !ok {
		t.Fatalf("Plugin.%s() = %v is not Oracle Versioning Format", getHandlerName(metric), got)
	}

	if !OraVersRx.MatchString(gotVers) {
		t.Fatalf("Plugin.%s() = %s is not Oracle Versioning Format", getHandlerName(metric), gotVers)
	}
}

func TestHandlers(t *testing.T) { //nolint:gocyclo,cyclop,paralleltest
	// When isolated test function is launched - impl.connMgr is nil.
	if impl.connMgr == nil {
		impl.Start()
		defer impl.Stop()
	}

	for metric := range plugin.Metrics {
		if metric == keyPing || metric == keyCustomQuery || metric == keyVersion {
			continue
		}

		got, err := impl.Export(metric, []string{config.OraURI, config.OraUser, config.OraPwd, config.OraSrv}, nil)
		if err != nil {
			t.Errorf("Plugin.%s() failed with error: %s", getHandlerName(metric), err.Error())

			continue
		}

		var obj any

		gotStr, ok := got.(string)
		if !ok {
			t.Errorf("Plugin.%s() = %+v, expected string Export result", getHandlerName(metric), got)

			continue
		}

		if err := json.Unmarshal([]byte(gotStr), &obj); err != nil {
			t.Errorf("Plugin.%s() = %+v, expected valid JSON.", getHandlerName(metric), got)

			continue
		}
	}

	// Test for ErrorCannotFetchData
	for _, conn := range impl.connMgr.Connections {
		conn.Client.Close() //nolint:gosec
	}

	for metric := range plugin.Metrics {
		if metric == keyPing || metric == keyCustomQuery {
			continue
		}

		_, err := impl.Export(metric, []string{config.OraURI, config.OraUser, config.OraPwd, config.OraSrv}, nil)
		if !errors.Is(err, zbxerr.ErrorCannotFetchData) {
			t.Errorf("Plugin.%s() should return %q if server is not working, got: %q",
				getHandlerName(metric), zbxerr.ErrorCannotFetchData, errors.Unwrap(err))

			continue
		}
	}

	// Test for ErrorTooManyParameters
	for metric := range plugin.Metrics {
		if metric == keyCustomQuery || metric == keyTablespaces {
			continue
		}

		_, err := impl.Export(
			metric,
			[]string{
				config.OraURI, config.OraUser, config.OraPwd, config.OraSrv,
				"excess_param", "excess_param", "excess_param",
			},
			nil)
		if !errors.Is(err, zbxerr.ErrorTooManyParameters) {
			t.Errorf("Plugin.%s() should fail if too many parameters passed", getHandlerName(metric))

			continue
		}
	}
}

func TestPlugin_Stop(t *testing.T) { //nolint:paralleltest
	t.Run("Connection manager must be deinitialized", func(t *testing.T) {
		impl.Stop()

		if impl.connMgr != nil {
			t.Error("Connection manager is not deinitialized")
		}
	})
}
