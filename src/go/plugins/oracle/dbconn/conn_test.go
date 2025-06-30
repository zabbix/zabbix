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

	"github.com/google/go-cmp/cmp"
)

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

			gotYarn := setCustomQuery(&mockLogger{}, tt.args.enabled, tt.args.path)
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
			args{newConnDet(t, "zabbix_mon", ""), &OraConn{username: "first"}},
			wants{1, &OraConn{username: "first"}},
			false,
		},
		{
			"+createSecond",
			[]fields{
				{newConnDet(t, "zabbix_mon", ""), &OraConn{username: "first"}},
			},
			args{newConnDet(t, "sys", "sysdba"), &OraConn{username: "second"}},
			wants{2, &OraConn{username: "second"}},
			false,
		},
		{
			"+getExisting",
			[]fields{
				{newConnDet(t, "zabbix_mon", ""), &OraConn{username: "first"}},
				{newConnDet(t, "sys", "sysdba"), &OraConn{username: "second"}},
			},
			args{newConnDet(t, "zabbix_mon", ""), &OraConn{username: "third"}},
			wants{2, &OraConn{username: "first"}},
			false,
		},
		{
			"-connectionNull",
			[]fields{},
			args{newConnDet(t, "zabbix_mon", ""), nil},
			wants{},
			true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			connMgr := NewConnManager(&mockLogger{}, opt)
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

			connMgr := NewConnManager(&mockLogger{}, opt)
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

			connMgr := NewConnManager(&mockLogger{}, opt)
			defer connMgr.Destroy()

			conn, err := connMgr.GetConnection(
				newConnDetNoCheck(t, "zabbix_mon", ""),
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

	connMgr := NewConnManager(&mockLogger{}, opt)
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
				newConnDetNoCheck(t, "zabbix_mon", ""),
				newConnDetNoCheck(t, "sys", "sysdba"),
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
						"ConnManager.closeUnused(): ConnManager.create() should create a connection, but got error: %s",
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
		name   string
		fields fields
		args   args
		want   want
	}{
		{
			"+returnExisting",
			fields{
				[]ConnDetails{
					newConnDetNoCheck(t, "zabbix_mon", ""),
					newConnDetNoCheck(t, "sys", "sysdba"),
				},
			},
			args{newConnDetNoCheck(t, "zabbix_mon", "")},
			want{"zabbix_mon", 2},
		},
		{
			"+createNew",
			fields{
				[]ConnDetails{
					newConnDetNoCheck(t, "sys", "sysdba"),
				},
			},
			args{newConnDetNoCheck(t, "zabbix_mon", "")},
			want{"zabbix_mon", 2},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			connMgr := NewConnManager(&mockLogger{}, opt)
			defer connMgr.Destroy()

			for _, v := range tt.fields.conDet {
				conn, err := connMgr.GetConnection(v)

				if err != nil || conn == nil {
					t.Fatalf(
						"ConnManager.GetConnection(): should create a connection, but got error: %s",
						err.Error(),
					)
				}
			}

			if len(connMgr.Connections) != len(tt.fields.conDet) {
				t.Fatalf(
					"ConnManager.GetConnection(): wrong connection cound seeded. Want: %d Got: %d",
					len(tt.fields.conDet), len(connMgr.Connections),
				)
			}

			gotOraConn, _ := connMgr.GetConnection(tt.args.conDet)
			if diff := cmp.Diff(tt.want.username, gotOraConn.username); diff != "" {
				t.Errorf("ConnManager.GetConnection(): returned connection mismatch = %s", diff)
			}

			if diff := cmp.Diff(tt.want.count, len(connMgr.Connections)); diff != "" {
				t.Errorf("ConnManager.GetConnection(): connection count mismatch = %s", diff)
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
