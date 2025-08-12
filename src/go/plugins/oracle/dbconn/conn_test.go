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

package dbconn

import (
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/godror/godror/dsn"
	"github.com/google/go-cmp/cmp"
	"golang.zabbix.com/agent2/plugins/oracle/mock"
	"golang.zabbix.com/sdk/uri"
)

var testCfg = testConfig{ //nolint:gochecknoglobals
	OraURI:  "localhost",
	OraUser: "ZABBIX_MON",
	OraPwd:  "zabbix",
	OraSrv:  "XE",
}

// testConfig type contains a mocked Oracle server connection credentials.
type testConfig struct {
	OraURI  string
	OraUser string
	OraPwd  string
	OraSrv  string
}

func Test_SplitUserAndPrivilege(t *testing.T) {
	t.Parallel()

	type args struct {
		userWithPrivilege string
	}

	tests := []struct {
		name          string
		args          args
		wantUser      string
		wantPrivilege dsn.AdminRole
		wantErr       bool
	}{
		{"+simpleUsername",
			args{"foobar"},
			"foobar", dsn.NoRole, false},
		{"+sysdbaWithMixedCase",
			args{"foobar AS sySdBa"},
			"foobar", dsn.SysDBA, false},
		{"+sysoperWithExtraSpaces",
			args{"foobar   as   sysoper"},
			"foobar", dsn.SysOPER, false},
		{"+sysasmUppercase",
			args{"foobar AS SYSASM"},
			"foobar", dsn.SysASM, false},
		{"+sysbackupPrivilege",
			args{"backup_user as sysbackup"},
			"backup_user", dsn.SysBACKUP, false},
		{"+sysdgForDataGuard",
			args{"dg_admin as sysdg"},
			"dg_admin", dsn.SysDG, false},
		{"+syskmFoKeystoreManagement",
			args{"key_mgr AS SYSKM"},
			"key_mgr", dsn.SysKM, false},
		{"+sysracForRACAdmin",
			args{"rac_user As SysRac"},
			"rac_user", dsn.SysRAC, false},
		{"+unknownPrivilege",
			args{"foobar as barfoo"},
			"foobar as barfoo", dsn.NoRole, false},
		{"-missingUser",
			args{""},
			"", dsn.NoRole, true},
		{"-tooManyParts",
			args{"foobar as sysdba extra"},
			"", dsn.NoRole, true},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			gotUser, gotPrivilege, err := SplitUserAndPrivilege(tt.args.userWithPrivilege)
			if (err != nil) != tt.wantErr {
				t.Fatalf("SplitUserAndPrivilege() error = %v, wantErr %v", err, tt.wantErr)
			}

			if tt.wantErr {
				return
			}

			if diff := cmp.Diff(tt.wantUser, gotUser); diff != "" {
				t.Errorf("splitUserAndPrivilege() gotUser mismatch (-want +got):\n%s", diff)
			}

			if diff := cmp.Diff(tt.wantPrivilege, gotPrivilege); diff != "" {
				t.Errorf("splitUserAndPrivilege() gotPrivilege mismatch (-want +got):\n%s", diff)
			}
		})
	}
}

func Test_setCustomQuery(t *testing.T) { //nolint:tparallel
	t.Parallel()

	type args struct {
		enabled bool
		path    string
	}

	type fields struct {
		fileName string
		content  string
	}

	type want struct {
		wantOut map[string]string
		wantErr bool
	}

	dir := t.TempDir()

	tests := []struct {
		name   string
		args   args
		fields []fields
		want   want
	}{
		{
			"+valid",
			args{true, dir},
			[]fields{
				{
					fileName: "users.sql",
					content:  "SELECT * FROM users",
				},
				{
					fileName: "customers.sql",
					content:  "SELECT * FROM customers",
				},
			},
			want{
				wantOut: map[string]string{
					"users.sql":     "SELECT * FROM users",
					"customers.sql": "SELECT * FROM customers",
				},
				wantErr: false,
			},
		},
		{
			"+disabled",
			args{false, dir},
			nil,
			want{
				wantOut: map[string]string{},
				wantErr: false,
			},
		},
		{
			"-invalidpath",
			args{true, "non/existent/path"},
			[]fields{
				{
					fileName: "will-not-be-saved.sql",
					content:  "SELECT * FROM will-not-be-saved",
				},
			},
			want{
				wantOut: map[string]string{},
				wantErr: true,
			},
		},
	}

	for _, tt := range tests { //nolint:paralleltest
		t.Run(tt.name, func(t *testing.T) {
			for _, v := range tt.fields {
				filePath := filepath.Join(tt.args.path, v.fileName)

				err := os.WriteFile(filePath, []byte(v.content), 0600)
				if err != nil && !tt.want.wantErr {
					t.Fatalf("failed to write file: %v", err)
				}
			}

			gotYarn := setCustomQuery(&mock.MockLogger{}, tt.args.enabled, tt.args.path)
			gotMap := gotYarn.All()

			if cmp.Diff(gotMap, tt.want.wantOut) != "" {
				t.Errorf("setCustomQuery() = %v, want %v", gotMap, tt.want.wantOut)
			}
		})
	}
}

func TestConnManager_setConn(t *testing.T) {
	t.Parallel()

	opt := Options{
		KeepAlive:            10 * time.Second,
		ConnectTimeout:       30 * time.Second,
		CallTimeout:          30 * time.Second,
		CustomQueriesEnabled: false,
		CustomQueriesPath:    "",
	}

	type fields struct {
		conDet  ConnDetails
		oraConn *OraConn
	}

	type args struct {
		conDet  ConnDetails
		oraConn *OraConn
	}

	type wants struct {
		wantConnCount int
		oraConn       *OraConn
	}

	tests := []struct {
		name    string
		fields  []fields
		args    args
		wants   wants
		wantErr bool
	}{
		{
			"+createFirst",
			[]fields{},
			args{newConnDet(t, "zabbix_mon", dsn.NoRole), &OraConn{username: "first"}},
			wants{1, &OraConn{username: "first"}},
			false,
		},
		{
			"+createSecond",
			[]fields{
				{newConnDet(t, "zabbix_mon", dsn.NoRole), &OraConn{username: "first"}},
			},
			args{newConnDet(t, "sys", dsn.SysDBA), &OraConn{username: "second"}},
			wants{2, &OraConn{username: "second"}},
			false,
		},
		{
			"+getExisting",
			[]fields{
				{newConnDet(t, "zabbix_mon", dsn.NoRole), &OraConn{username: "first"}},
				{newConnDet(t, "sys", dsn.SysDBA), &OraConn{username: "second"}},
			},
			args{newConnDet(t, "zabbix_mon", dsn.NoRole), &OraConn{username: "third"}},
			wants{2, &OraConn{username: "first"}},
			false,
		},
		{
			"-connectionNull",
			[]fields{},
			args{newConnDet(t, "zabbix_mon", dsn.NoRole), nil},
			wants{},
			true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			connMgr := NewConnManager(&mock.MockLogger{}, &opt)
			defer connMgr.Destroy()

			for _, ff := range tt.fields {
				_, err := connMgr.setConn(ff.conDet, ff.oraConn)
				if err != nil {
					t.Fatalf("ConnManager.setConn(): error = %v", err)
				}
			}

			gotConn, err := connMgr.setConn(tt.args.conDet, tt.args.oraConn)
			if err != nil && !tt.wantErr {
				t.Fatalf("ConnManager.setConn(): error = %v, wantErr %v", err, tt.wantErr)
			}

			diff := cmp.Diff(len(connMgr.Connections), tt.wants.wantConnCount)
			if diff != "" && !tt.wantErr {
				t.Errorf("ConnManager.setConn(): connection count= %s", diff)
			}

			diff = cmp.Diff(gotConn, tt.wants.oraConn, cmp.Comparer(compareOraCon))
			if diff != "" {
				t.Errorf("ConnManager.setConn(): returned ora connection= %s", diff)
			}
		})
	}
}

func TestConnManager_getConn(t *testing.T) {
	t.Parallel()

	opt := Options{
		KeepAlive:            10 * time.Second,
		ConnectTimeout:       30 * time.Second,
		CallTimeout:          30 * time.Second,
		CustomQueriesEnabled: false,
		CustomQueriesPath:    "",
	}

	type fields struct {
		conDet  ConnDetails
		oraConn *OraConn
	}

	type args struct {
		conDet ConnDetails
	}

	type wants struct {
		oraConn *OraConn
	}

	tests := []struct {
		name    string
		fields  []fields
		args    args
		wants   wants
		wantErr bool
	}{
		{
			"+foundNothingInEmpty",
			[]fields{},
			args{newConnDet(t, "zabbix_mon", "")},
			wants{nil},
			false,
		},
		{
			"+foundInOneLength",
			[]fields{
				{newConnDet(t, "zabbix_mon", ""), &OraConn{username: "first"}},
			},
			args{newConnDet(t, "zabbix_mon", "")},
			wants{&OraConn{username: "first"}},
			false,
		},
		{
			"+foundInMultiple",
			[]fields{
				{newConnDet(t, "zabbix_mon", ""), &OraConn{username: "first"}},
				{newConnDet(t, "sys", "sysdba"), &OraConn{username: "second"}},
			},
			args{newConnDet(t, "sys", "sysdba")},
			wants{&OraConn{username: "second"}},
			false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			connMgr := NewConnManager(&mock.MockLogger{}, &opt)
			defer connMgr.Destroy()

			for _, ff := range tt.fields {
				connMgr.Connections[ff.conDet] = ff.oraConn
			}

			gotConn := connMgr.getConn(tt.args.conDet)

			if diff := cmp.Diff(gotConn, tt.wants.oraConn, cmp.Comparer(compareOraCon)); diff != "" {
				t.Errorf("ConnManager.getConn(): found ora connection= %s", diff)
			}
		})
	}
}

func TestConnManager_closeUnused(t *testing.T) {
	t.Parallel()

	type fields struct {
		keepAlive time.Duration
	}

	type wants struct {
		connCount int
	}

	tests := []struct {
		name   string
		fields fields
		wants  wants
	}{
		{
			"+noUnused",
			fields{keepAlive: 5 * time.Second},
			wants{connCount: 1},
		},
		{
			"+deleteUnused",
			fields{keepAlive: 1 * time.Microsecond},
			wants{connCount: 0},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			opt := Options{
				KeepAlive:            tt.fields.keepAlive,
				ConnectTimeout:       30 * time.Second,
				CallTimeout:          30 * time.Second,
				CustomQueriesEnabled: false,
				CustomQueriesPath:    "",
			}

			connMgr := NewConnManager(&mock.MockLogger{}, &opt)
			connMgr.versionCheckF = mock.ServerVersionMock
			defer connMgr.Destroy()

			conn, err := connMgr.GetConnection(
				newConnDet(t, "zabbix_mon", ""),
			)
			if err != nil || conn == nil {
				t.Fatalf(
					"ConnManager.closeUnused():should create a connection, but got error: %s",
					err.Error(),
				)
			}

			time.Sleep(10 * time.Microsecond)

			connMgr.closeUnused()

			if diff := cmp.Diff(len(connMgr.Connections), tt.wants.connCount); diff != "" {
				t.Errorf("ConnManager.closeUnused(): connection count= %s", diff)
			}
		})
	}
}

func TestConnManager_closeAll(t *testing.T) { //nolint:tparallel
	t.Parallel()

	opt := Options{
		KeepAlive:            10 * time.Second,
		ConnectTimeout:       30 * time.Second,
		CallTimeout:          30 * time.Second,
		CustomQueriesEnabled: false,
		CustomQueriesPath:    "",
	}

	connMgr := NewConnManager(&mock.MockLogger{}, &opt)
	connMgr.versionCheckF = mock.ServerVersionMock
	defer connMgr.Destroy()

	type fields struct {
		conDet []ConnDetails
	}

	tests := []struct {
		name   string
		fields fields
	}{
		{
			"+close",
			fields{[]ConnDetails{
				newConnDet(t, "zabbix_mon", ""),
				newConnDet(t, "sys", "sysdba"),
			},
			},
		},
		{
			"+nothingToClose",
			fields{[]ConnDetails{}},
		},
	}

	for _, tt := range tests { //nolint:paralleltest
		t.Run(tt.name, func(t *testing.T) {
			for _, v := range tt.fields.conDet {
				conn, err := connMgr.GetConnection(v)
				if err != nil || conn == nil {
					t.Fatalf(
						"ConnManager.closeUnused(): ConnManager.createConn() should create a connection, but "+
							"got the error: %s",
						err.Error(),
					)
				}
			}

			connMgr.closeAll()

			if diff := cmp.Diff(len(connMgr.Connections), 0); diff != "" {
				t.Errorf("ConnManager.close(): connection count= %s", diff)
			}
		})
	}
}

//nolint:gocyclo,cyclop
func TestConnManager_GetConnection(t *testing.T) {
	t.Parallel()

	opt := Options{
		KeepAlive:            10 * time.Second,
		ConnectTimeout:       30 * time.Second,
		CallTimeout:          30 * time.Second,
		CustomQueriesEnabled: false,
		CustomQueriesPath:    "",
	}

	type fields struct {
		conDet []ConnDetails
	}

	type args struct {
		conDet ConnDetails
	}

	type want struct {
		username string
		count    int
	}

	tests := []struct {
		name    string
		fields  fields
		args    args
		want    want
		wantErr bool
	}{
		{
			"+returnExisting",
			fields{
				[]ConnDetails{
					newConnDet(t, "zabbix_mon", ""),
					newConnDet(t, "sys", "sysdba"),
				},
			},
			args{newConnDet(t, "zabbix_mon", "")},
			want{"zabbix_mon", 2},
			false,
		},
		{
			"+createNew",
			fields{
				[]ConnDetails{
					newConnDet(t, "sys", "sysdba"),
				},
			},
			args{newConnDet(t, "zabbix_mon", "")},
			want{"zabbix_mon", 2},
			false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			connMgr := NewConnManager(&mock.MockLogger{}, &opt)
			connMgr.versionCheckF = mock.ServerVersionMock
			defer connMgr.Destroy()

			for _, v := range tt.fields.conDet {
				conn, err := connMgr.GetConnection(v)
				if (err != nil || conn == nil) && !tt.wantErr {
					t.Fatalf(
						"ConnManager.GetConnection(): should create a connection, but got error: %s",
						err.Error(),
					)
				}
			}

			if len(connMgr.Connections) != len(tt.fields.conDet) {
				t.Fatalf(
					"ConnManager.GetConnection(): wrong connection count seeded. Want: %d Got: %d",
					len(tt.fields.conDet), len(connMgr.Connections),
				)
			}

			gotOraConn, err := connMgr.GetConnection(tt.args.conDet)
			if err != nil {
				if !tt.wantErr {
					t.Fatalf("ConnManager.GetConnection(): error = %v", err)
				}

				return
			}

			if diff := cmp.Diff(tt.want.username, gotOraConn.username); diff != "" {
				t.Errorf("ConnManager.GetConnection(): returned connection mismatch = %s", diff)
			}

			if diff := cmp.Diff(tt.want.count, len(connMgr.Connections)); diff != "" {
				if !tt.wantErr {
					t.Errorf("ConnManager.GetConnection(): connection count mismatch = %s", diff)
				}
			}
		})
	}
}

func Test_isOnlyHostnameOrIP(t *testing.T) { //nolint:tparallel
	t.Parallel()

	type args struct {
		rawURI string
	}

	type want struct {
		isURI   bool
		wantErr bool
	}

	tests := []struct {
		name string
		args args
		want want
	}{
		{
			"+isNameWithPort",
			args{"zbx_next:1599"},
			want{true, false},
		},
		{
			"+namePartlyPort",
			args{"zbx_next:"},
			want{false, false},
		},
		{
			"+isNameWithSchema",
			args{"tcp://zbx_next"},
			want{true, false},
		},
		{
			"+isNameWithSchemaPort",
			args{"tcp://zbx_next:1599"},
			want{true, false},
		},
		{
			"+isNameWithSchemaHttp",
			args{"http://zbx_next"},
			want{true, false},
		},
		{
			"+notName",
			args{"zbx_next"},
			want{false, false},
		},
		{
			"+notNameWithMockedDefaultSchema",
			args{"tcpzbx_next"},
			want{false, false},
		},
		{
			"+nameStartingWithBreakspacesNoSchema",
			args{"   zbx_next"},
			want{false, false},
		},
		{
			"+nameStartingWithBreakspaces",
			args{"   tcp://zbx_next"},
			want{true, false},
		},
		{
			"+nameWithPartlySchemaSlashes",
			args{"tcp//zbx_next"},
			want{false, false},
		},
		{
			"+nameWithPartlySchemaSemicol",
			args{"tcp:zbx_next"},
			want{false, true},
		},
		{
			"+nameWithPartlyOnlySlashes",
			args{"tcp//zbx_next"},
			want{false, false},
		},
		{
			"+IP",
			args{"127.0.0.1"},
			want{true, false},
		},
		{
			"+IPWithPort",
			args{"127.1.1.1:1599"},
			want{true, false},
		},
		{
			"+IPWithPartlyPort",
			args{"127.1.1.1:"},
			want{false, false},
		},
		{
			"+IPWithSchema",
			args{"tcp://127.1.1.1"},
			want{true, false},
		},
		{
			"+IPWithSchemaPort",
			args{"tcp://127.1.1.1:1599"},
			want{true, false},
		},
		{
			"+IPWithSchemaHttp",
			args{"http://127.1.1.1"},
			want{true, false},
		},
		{
			"+IPWithWithHostname",
			args{"zbx_tns127.1.1.1"},
			want{false, false},
		},
		{
			"-notNameFormat",
			args{"+%%%::&&"},
			want{false, true},
		},
		{
			"-empty",
			args{""},
			want{false, true},
		},
		{
			"-whitespaces",
			args{"       "},
			want{false, true},
		},
	}

	for _, tt := range tests { //nolint:paralleltest
		t.Run(tt.name, func(t *testing.T) {
			isURI, err := isOnlyHostnameOrIP(tt.args.rawURI)
			if err != nil && !tt.want.wantErr {
				t.Errorf("isOnlyHostnameOrIP() error = %v, want nil", err)
			}

			if isURI != tt.want.isURI {
				t.Errorf("isOnlyHostnameOrIP() isURI = %t, want %t", isURI, tt.want.isURI)
			}
		})
	}
}

func compareOraCon(x, y *OraConn) bool {
	if x == nil && y == nil {
		return true
	}

	if x == nil || y == nil {
		return false
	}

	return x.username == y.username
}

func newConnDet(t *testing.T, username string, privilege dsn.AdminRole) ConnDetails {
	t.Helper()

	u, _ := uri.NewWithCreds(
		testCfg.OraURI+"?service="+testCfg.OraSrv,
		username,
		"zabbix",
		URIDefaults)

	return ConnDetails{*u, privilege, false}
}
