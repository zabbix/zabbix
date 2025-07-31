//go:build integration_tests

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
	"strings"
	"testing"
	"time"

	"golang.zabbix.com/agent2/plugins/oracle/dbconn"
	"golang.zabbix.com/agent2/plugins/oracle/handlers"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

// testConfig contains connection properties to real Oracle environment.
type testConfig struct {
	OraHostname string
	OraIP       string

	OraUser string
	OraPwd  string
	OraSrv  string
}

var (
	testCfg   *testConfig //nolint:gochecknoglobals
	OraVersRx = regexp.MustCompile(`^\d{2}\.\d+\.\d+\.\d+\.\d+$`)
)

func TestMain(m *testing.M) {
	testCfg = &testConfig{
		OraHostname: os.Getenv("ORA_HOSTNAME"),
		OraIP:       os.Getenv("ORA_IP"),

		OraUser: os.Getenv("ORA_USER"),
		OraPwd:  os.Getenv("ORA_PWD"),
		OraSrv:  os.Getenv("ORA_SRV"),
	}

	if testCfg.OraIP == "" || testCfg.OraUser == "" || testCfg.OraPwd == "" || testCfg.OraSrv == "" {
		fmt.Println( //nolint:forbidigo
			"    ==SETUP NEEDED==\n" +
				"1) Environment variables ORA_IP, ORA_USER, ORA_PWD and ORA_SRV must be set to run tests.\n" +
				"  The variable ORA_HOSTNAME is optional but recommended.\n" +
				"2) The TNS value must be inserted in the appropriate directory of your system, e.g.,\n+" +
				"  /opt/oracle/instantclient_23_7/network/admin/tnsnames.ora:\n" +
				"  zbx_tns = (DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=<ORA_IP or ORA_HOSTNAME>)(PORT=1521))\n" +
				"  (CONNECT_DATA=(SERVICE_NAME=<ORA_SRV>))), e.g., zbx_tns = (DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)\n" +
				"  (HOST=localhost)(PORT=1521))(CONNECT_DATA=(SERVICE_NAME=XE)))",
		)
		fmt.Println( //nolint:forbidigo
			"    ==ADDITIONAL HINTS==\n" +
				"Use -test.short to avoid long running tests (some negative tests run long because of Oracle client timeouts).\n" +
				"Usage: \n" +
				"CLI: go test -test.short <pckg>\n" +
				"GoLAND: add -test.short to Run/Debug config field 'Program arguments'",
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

//nolint:paralleltest
func TestPlugin_Export_TNSDisabled(t *testing.T) {
	// Set here because the plugin's config file has not been loaded and default ResolveTNS=true
	impl.options.ResolveTNS = false

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

	tests := []struct {
		name       string
		p          *Plugin
		args       args
		wantResult any
		wantErr    error
		longTest   bool
		//if hasHostname==true and hostname is not specified, the test will be omitted
		hasHostname bool
	}{
		{
			name: "+hostname",
			p:    &impl,
			args: args{keyPing,
				[]string{testCfg.OraHostname, testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult:  handlers.PingOk,
			wantErr:     nil,
			hasHostname: true,
		},
		{
			name: "+hostnameWithSchema",
			p:    &impl,
			args: args{keyPing,
				[]string{"tcp://" + testCfg.OraHostname,
					testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult:  handlers.PingOk,
			wantErr:     nil,
			hasHostname: true,
		},
		{
			name: "+hostnameWithPort",
			p:    &impl,
			args: args{keyPing,
				[]string{testCfg.OraHostname + ":1521",
					testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult:  handlers.PingOk,
			wantErr:     nil,
			hasHostname: true,
		},
		{
			name: "+hostnameWithSchemaPort",
			p:    &impl,
			args: args{keyPing,
				[]string{fmt.Sprintf("tcp://%s:1521", testCfg.OraHostname),
					testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult:  handlers.PingOk,
			wantErr:     nil,
			hasHostname: true,
		},
		{
			name: "+IP",
			p:    &impl,
			args: args{keyPing,
				[]string{testCfg.OraIP,
					testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult: handlers.PingOk,
			wantErr:    nil,
		},
		{
			name: "+IPwithSchema",
			p:    &impl,
			args: args{keyPing,
				[]string{"tcp://" + testCfg.OraIP,
					testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult: handlers.PingOk,
			wantErr:    nil,
		},
		{
			name: "+IPwithPort",
			p:    &impl,
			args: args{keyPing,
				[]string{testCfg.OraIP + ":1521",
					testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult: handlers.PingOk,
			wantErr:    nil,
		},
		{
			name: "+IPwithSchemaPort",
			p:    &impl,
			args: args{keyPing,
				[]string{fmt.Sprintf("tcp://%s:1521", testCfg.OraIP),
					testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult: handlers.PingOk,
			wantErr:    nil,
		},
		{
			name: "+IPnoService",
			p:    &impl,
			args: args{keyPing,
				[]string{testCfg.OraIP,
					testCfg.OraUser, testCfg.OraPwd},
				nil,
			},
			wantResult: handlers.PingOk,
			wantErr:    nil,
		},
		{
			// Although ResolveTNS is false, the connection by TNS name value anyway should work.
			name: "+TNSValueByHostname",
			p:    &impl,
			args: args{keyPing,
				[]string{fmt.Sprintf("(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=%s)(PORT=1521))"+
					"(CONNECT_DATA=(SERVICE_NAME=%s)))", testCfg.OraHostname, testCfg.OraSrv),
					testCfg.OraUser,
					testCfg.OraPwd,
					testCfg.OraSrv,
				},
				nil,
			},
			wantResult:  handlers.PingOk,
			wantErr:     nil,
			hasHostname: true,
		},
		// Although ResolveTNS is false, the connection by TNS name value anyway should work.
		{
			name: "+TNSValueByIP",
			p:    &impl,
			args: args{keyPing,
				[]string{fmt.Sprintf("(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=%s)(PORT=1521))"+
					"(CONNECT_DATA=(SERVICE_NAME=%s)))", testCfg.OraIP, testCfg.OraSrv),
					testCfg.OraUser,
					testCfg.OraPwd,
					testCfg.OraSrv,
				},
				nil,
			},
			wantResult: handlers.PingOk,
			wantErr:    nil,
		},
		{
			name:        "-hostnameOnly",
			p:           &impl,
			args:        args{keyPing, []string{testCfg.OraHostname}, nil},
			wantResult:  handlers.PingFailed,
			wantErr:     dbconn.ErrMissingParamUser,
			hasHostname: true,
		},
		{
			name:       "-unknownHostname",
			p:          &impl,
			args:       args{keyUser, []string{"fake", testCfg.OraUser}, nil},
			wantResult: nil,
			wantErr:    errors.New("ORA-12545: Connect failed because target host or object does not exist"),
			longTest:   true,
		},
		{
			name: "-hostnameWithUser",
			p:    &impl,
			args: args{keyPing,
				[]string{testCfg.OraHostname, testCfg.OraUser},
				nil,
			},
			wantResult:  handlers.PingFailed,
			wantErr:     nil,
			hasHostname: true,
		},
		{
			name: "-hostnameWithUserService",
			p:    &impl,
			args: args{keyPing,
				[]string{testCfg.OraHostname, testCfg.OraUser, "", testCfg.OraSrv},
				nil,
			},
			wantResult:  handlers.PingFailed,
			wantErr:     nil,
			hasHostname: true,
		},
		{
			name: "-IPonly",
			p:    &impl,
			args: args{keyPing,
				[]string{testCfg.OraIP}, nil,
			},
			wantResult: handlers.PingFailed,
			wantErr:    dbconn.ErrMissingParamUser,
		},
		{
			name:       "-unknownIP",
			p:          &impl,
			args:       args{keyUser, []string{"254.254.254.100", testCfg.OraUser}, nil},
			wantResult: nil,
			wantErr:    errors.New("ORA-12545: Connect failed because target host or object does not exist"),
			longTest:   true,
		},
		{
			name: "-IPwithUser",
			p:    &impl,
			args: args{keyPing,
				[]string{testCfg.OraIP, testCfg.OraUser},
				nil,
			},
			wantResult: handlers.PingFailed,
			wantErr:    nil,
		},
		{
			name: "-IPwithUserService",
			p:    &impl,
			args: args{keyPing,
				[]string{testCfg.OraIP, testCfg.OraUser, "", testCfg.OraSrv},
				nil,
			},
			wantResult: handlers.PingFailed,
			wantErr:    nil,
		},
		{
			name: "-TNSKey",
			p:    &impl,
			args: args{keyPing,
				[]string{"zbx_tns", testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult: handlers.PingFailed,
			wantErr:    nil,
			longTest:   true,
		},
		{
			name: "-TNSKeyNoPassword",
			p:    &impl,
			args: args{keyPing,
				[]string{"zbx_tns", testCfg.OraUser, "", testCfg.OraSrv},
				nil,
			},
			wantResult: handlers.PingFailed,
			wantErr:    nil,
			longTest:   true,
		},
		{
			name: "-brokenTNSValue",
			p:    &impl,
			args: args{keyPing,
				[]string{"DESCRIPTION=(AD..", testCfg.OraUser, testCfg.OraPwd},
				nil,
			},
			wantResult: handlers.PingFailed,
			wantErr:    nil,
		},
	}

	for _, tt := range tests { //nolint:paralleltest
		t.Run(tt.name, func(t *testing.T) {
			if testing.Short() && tt.longTest {
				t.Skip("Skipping long test (short mode enabled)!")
			}

			if tt.hasHostname && testCfg.OraHostname == "" {
				t.Skip("Skipping test as the environmental variable ORA_HOSTNAME is unset!")
			}

			impl.connMgr.Opt.ResolveTNS = false
			impl.connMgr.Opt.ConnectTimeout = 5 * time.Second

			gotResult, err := tt.p.Export(tt.args.key, tt.args.params, tt.args.ctx)

			if err != nil {
				if tt.wantErr == nil || !strings.Contains(err.Error(), tt.wantErr.Error()) {
					t.Errorf("Plugin.Export() error = %v, wantErr %v", err, tt.wantErr)

					return
				}

				return
			}

			if !reflect.DeepEqual(gotResult, tt.wantResult) {
				t.Errorf("Plugin.Export() = %v, want %v", gotResult, tt.wantResult)
			}
		})
	}
}

//nolint:paralleltest
func TestPlugin_Export_TNSEnabled(t *testing.T) {
	// Set here because the plugin's config file has not been loaded and default ResolveTNS=true
	impl.options.ResolveTNS = false

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

	tests := []struct {
		name        string
		p           *Plugin
		args        args
		wantResult  any
		wantErr     error
		longTest    bool
		hasHostname bool
	}{
		{
			name: "+TNSKey", // the valid tns name description w/ the key 'zbx_tns' must be added to tnsnames.ora.
			p:    &impl,
			args: args{keyPing,
				[]string{"zbx_tns", testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult: handlers.PingOk,
			wantErr:    nil,
		},
		{
			// The connection by TNS name does not depend on ResolveTNS param.
			name: "+TNSValueByHostname",
			p:    &impl,
			args: args{keyPing,
				[]string{fmt.Sprintf("(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=%s)(PORT=1521))"+
					"(CONNECT_DATA=(SERVICE_NAME=%s)))", testCfg.OraHostname, testCfg.OraSrv),
					testCfg.OraUser,
					testCfg.OraPwd,
					testCfg.OraSrv,
				},
				nil,
			},
			wantResult:  handlers.PingOk,
			wantErr:     nil,
			hasHostname: true,
		},
		{
			// The connection by TNS name does not depend on ResolveTNS param.
			name: "+TNSValueByIP",
			p:    &impl,
			args: args{keyPing,
				[]string{fmt.Sprintf("(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=%s)(PORT=1521))"+
					"(CONNECT_DATA=(SERVICE_NAME=%s)))", testCfg.OraIP, testCfg.OraSrv),
					testCfg.OraUser,
					testCfg.OraPwd,
					testCfg.OraSrv,
				},
				nil,
			},
			wantResult: handlers.PingOk,
			wantErr:    nil,
		},
		{
			//Oracle client looks hostname up in TNS - doesn't find it; tries as hostname - succeeds (godror functionality).
			name: "+hostname",
			p:    &impl,
			args: args{keyPing,
				[]string{testCfg.OraHostname, testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult:  handlers.PingOk,
			wantErr:     nil,
			hasHostname: true,
		},
		{
			name: "+hostnameWithSchema",
			p:    &impl,
			args: args{keyPing,
				[]string{"tcp://" + testCfg.OraHostname,
					testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult:  handlers.PingOk,
			wantErr:     nil,
			hasHostname: true,
		},
		{
			name: "+hostnameWithPort",
			p:    &impl,
			args: args{keyPing,
				[]string{testCfg.OraHostname + ":1521",
					testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult:  handlers.PingOk,
			wantErr:     nil,
			hasHostname: true,
		},
		{
			name: "+hostnameWithSchemaPort",
			p:    &impl,
			args: args{keyPing,
				[]string{fmt.Sprintf("tcp://%s:1521", testCfg.OraHostname),
					testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult:  handlers.PingOk,
			wantErr:     nil,
			hasHostname: true,
		},
		{
			name: "+IP",
			p:    &impl,
			args: args{keyPing,
				[]string{testCfg.OraIP,
					testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult: handlers.PingOk,
			wantErr:    nil,
		},
		{
			name: "+IPwithSchema",
			p:    &impl,
			args: args{keyPing,
				[]string{"tcp://" + testCfg.OraIP,
					testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult: handlers.PingOk,
			wantErr:    nil,
		},
		{
			name: "+IPwithPort",
			p:    &impl,
			args: args{keyPing,
				[]string{testCfg.OraIP + ":1521",
					testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult: handlers.PingOk,
			wantErr:    nil,
		},
		{
			name: "+IPwithSchemaPort",
			p:    &impl,
			args: args{keyPing,
				[]string{fmt.Sprintf("tcp://%s:1521", testCfg.OraIP),
					testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult: handlers.PingOk,
			wantErr:    nil,
		},
		{
			name: "+IPnoService",
			p:    &impl,
			args: args{keyPing,
				[]string{testCfg.OraIP,
					testCfg.OraUser, testCfg.OraPwd},
				nil,
			},
			wantResult: handlers.PingOk,
			wantErr:    nil,
		},
		{
			// Although ResolveTNS is false, the connection by TNS name value anyway should work.
			name: "+TNSValueByHostname",
			p:    &impl,
			args: args{keyPing,
				[]string{fmt.Sprintf("(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=%s)(PORT=1521))"+
					"(CONNECT_DATA=(SERVICE_NAME=%s)))", testCfg.OraHostname, testCfg.OraSrv),
					testCfg.OraUser,
					testCfg.OraPwd,
					testCfg.OraSrv,
				},
				nil,
			},
			wantResult:  handlers.PingOk,
			wantErr:     nil,
			hasHostname: true,
		},
		{
			name: "+TNSValueByIP",
			p:    &impl,
			args: args{keyPing,
				[]string{fmt.Sprintf("(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=%s)(PORT=1521))"+
					"(CONNECT_DATA=(SERVICE_NAME=%s)))", testCfg.OraIP, testCfg.OraSrv),
					testCfg.OraUser,
					testCfg.OraPwd,
					testCfg.OraSrv,
				},
				nil,
			},
			wantResult: handlers.PingOk,
			wantErr:    nil,
		},
		{
			// It will work for a few minutes until reach oracle client timeout. If so, then the test is successful.
			name:       "-unknownTNSandHostname",
			p:          &impl,
			args:       args{keyUser, []string{"fake", testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv}, nil},
			wantResult: nil,
			wantErr:    errors.New("ORA-12154: Cannot connect to database. Cannot find alias"),
			longTest:   true,
		},
		{
			name: "-hostnameWithUser",
			p:    &impl,
			args: args{keyPing,
				[]string{testCfg.OraHostname, testCfg.OraUser},
				nil,
			},
			wantResult:  handlers.PingFailed,
			wantErr:     nil,
			hasHostname: true,
		},
		{
			name: "-IPnoPwd",
			p:    &impl,
			args: args{keyPing,
				[]string{testCfg.OraIP, testCfg.OraUser},
				nil,
			},
			wantResult: handlers.PingFailed,
			wantErr:    nil,
		},
		{
			name:       "-unknownIP",
			p:          &impl,
			args:       args{keyUser, []string{"254.254.254.100", testCfg.OraUser}, nil},
			wantResult: nil,
			wantErr:    errors.New("ORA-12545: Connect failed because target host or object does not exist"),
			longTest:   true,
		},
		{
			name: "-TNSKeyWithPort_TreatedAsHostname",
			p:    &impl,
			args: args{keyPing,
				[]string{"zbx_tns:9999", testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult: handlers.PingFailed,
			wantErr:    nil,
			longTest:   true,
		},
		{
			name: "-TNSKeyWithSchema_TreatedAsHostname",
			p:    &impl,
			args: args{keyPing,
				[]string{"tcp://zbx_tns", testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult: handlers.PingFailed,
			wantErr:    nil,
			longTest:   true,
		},
		{
			name: "-TNSValueWrongFormat-Trailing",
			p:    &impl,
			args: args{keyPing,
				[]string{
					"(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=any)(PORT=1521))(CONNECT_DATA=(SERVICE_NAME=xe___",
					testCfg.OraUser,
					testCfg.OraPwd,
					testCfg.OraSrv,
				},
				nil,
			},
			wantResult: handlers.PingFailed,
			wantErr:    nil,
		},
		{
			name: "-TNSValueWrongFormat-Leading",
			p:    &impl,
			args: args{keyPing,
				[]string{"(DESCRIPTION=", testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv},
				nil,
			},
			wantResult: handlers.PingFailed,
			wantErr:    nil,
		},
	}

	for _, tt := range tests { //nolint:paralleltest
		t.Run(tt.name, func(t *testing.T) {
			if testing.Short() && tt.longTest {
				t.Skip("Skipping long test (short mode enabled)!")
			}

			if tt.hasHostname && testCfg.OraHostname == "" {
				t.Skip("Skipping test as the environmental variable ORA_HOSTNAME is unset!")
			}

			impl.connMgr.Opt.ResolveTNS = true
			impl.connMgr.Opt.ConnectTimeout = 5 * time.Second

			gotResult, err := tt.p.Export(tt.args.key, tt.args.params, tt.args.ctx)

			if err != nil {
				if tt.wantErr == nil || !strings.Contains(err.Error(), tt.wantErr.Error()) {
					t.Errorf("Plugin.Export() error = %v, wantErr %v", err, tt.wantErr)

					return
				}

				return
			}

			if !reflect.DeepEqual(gotResult, tt.wantResult) {
				t.Errorf("Plugin.Export() = %v, want %v", gotResult, tt.wantResult)
			}
		})
	}
}

//nolint:paralleltest
func Test_PingHandler(t *testing.T) {
	// When isolated test function is launched - impl.connMgr is nil.
	if impl.connMgr == nil {
		impl.Start()
		defer impl.Stop()
	}

	metric := keyPing

	got, err := impl.Export(metric, []string{testCfg.OraIP, testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv}, nil)
	if err != nil {
		t.Fatalf("Plugin.%s() failed with error: %s", getHandlerName(t, metric), err.Error())
	}

	gotNum, ok := got.(int)
	if !ok {
		t.Fatalf("Plugin.%s() = %d, expected = integer", getHandlerName(t, metric), got)
	}

	if gotNum != handlers.PingOk {
		t.Fatalf("Plugin.%s() = %d, expected = %d", getHandlerName(t, metric), gotNum, handlers.PingOk)
	}
}

//nolint:paralleltest
func Test_VersionHandler(t *testing.T) {
	// When isolated test function is launched - impl.connMgr is nil.
	if impl.connMgr == nil {
		impl.Start()
		defer impl.Stop()
	}

	metric := keyVersion

	got, err := impl.Export(metric, []string{testCfg.OraIP,
		testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv}, nil)
	if err != nil {
		t.Fatalf("Plugin.%s() failed with error: %s", getHandlerName(t, metric), err.Error())
	}

	gotVers, ok := got.(string)
	if !ok {
		t.Fatalf("Plugin.%s() = %v is not Oracle Versioning Format", getHandlerName(t, metric), got)
	}

	if !OraVersRx.MatchString(gotVers) {
		t.Fatalf("Plugin.%s() = %s is not Oracle Versioning Format", getHandlerName(t, metric), gotVers)
	}
}

//nolint:gocyclo,cyclop,paralleltest
func Test_HandlerResultFormat(t *testing.T) {
	//When isolated test function is launched - impl.connMgr is nil.
	if impl.connMgr == nil {
		impl.Start()
		defer impl.Stop()
	}

	type test struct {
		name   string
		metric string
	}

	var tests []test

	// Generate test slice.
	for metric := range plugin.Metrics {
		if metric == keyPing || metric == keyCustomQuery || metric == keyVersion {
			// These handlers have a structured - non-error return in case of the negative replay.
			continue
		}
		tests = append(tests, test{"+" + metric, metric})
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := impl.Export(tt.metric, []string{testCfg.OraIP,
				testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv}, nil)
			if err != nil {
				t.Fatalf("Plugin.%s() failed with error: %s", getHandlerName(t, tt.metric), err.Error())
			}

			var obj any
			gotStr, ok := got.(string)

			if !ok {
				t.Fatalf("Plugin.%s() = %+v, expected string Export result", getHandlerName(t, tt.metric), got)
			}

			if err := json.Unmarshal([]byte(gotStr), &obj); err != nil {
				t.Fatalf("Plugin.%s() = %+v, expected valid JSON.", getHandlerName(t, tt.metric), got)
			}
		})
	}
}

//nolint:gocyclo,cyclop,paralleltest
func Test_HandlersResultClosedDb(t *testing.T) {
	//When isolated test function is launched - impl.connMgr is nil.
	if impl.connMgr == nil {
		impl.Start()
		defer impl.Stop()
	}
	connDetails, err := dbconn.NewConnDetails(
		testCfg.OraIP,
		testCfg.OraUser,
		testCfg.OraPwd,
		testCfg.OraSrv,
	)
	if err != nil {
		t.Fatalf("Error creating connection details: %v", err)
	}

	conn, err := impl.connMgr.GetConnection(*connDetails)
	if err != nil {
		t.Fatalf("Error creating connection: %v", err)
	}

	//Close the connection to simulate some problem with connection
	err = conn.Client.Close()
	if err != nil {
		t.Fatalf("Error closing connection: %v", err)
	}

	type test struct {
		name   string
		metric string
	}

	var tests []test

	// Generate test slice.
	for metric := range plugin.Metrics {
		if metric == keyPing || metric == keyCustomQuery {
			continue
		}
		tests = append(tests, test{"-" + metric, metric})
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			_, err := impl.Export(tt.metric, []string{testCfg.OraIP, testCfg.OraUser, testCfg.OraPwd, testCfg.OraSrv}, nil)

			if !errors.Is(err, zbxerr.ErrorCannotFetchData) {
				t.Errorf("Plugin.%s() should return %q if server is not working, got: %q",
					getHandlerName(t, tt.metric), zbxerr.ErrorCannotFetchData, errors.Unwrap(err))
			}
		})
	}
}

//nolint:paralleltest
func TestPlugin_Stop(t *testing.T) {
	t.Run("Connection manager must be deinitialized", func(t *testing.T) {
		impl.Stop()

		if impl.connMgr != nil {
			t.Error("Connection manager is not deinitialized")
		}
	})
}
