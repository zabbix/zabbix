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
	"context"
	"database/sql"
	"fmt"
	"log"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"
	"golang.zabbix.com/agent2/plugins/oracle/dbconn"
	"golang.zabbix.com/agent2/plugins/oracle/mock"
	"golang.zabbix.com/sdk/uri"
)

func TestOraConn_Query(t *testing.T) { //nolint:tparallel
	t.Parallel()

	opt := dbconn.Options{
		KeepAlive:            100 * time.Second,
		ConnectTimeout:       100 * time.Second,
		CallTimeout:          100 * time.Second,
		CustomQueriesEnabled: false,
		CustomQueriesPath:    "",
	}

	connMgr := dbconn.NewConnManager(&mock.MockLogger{}, &opt)
	defer connMgr.Destroy()

	oraCon, err := connMgr.GetConnection(newConnDetDefault(t, testCfg))
	if err != nil {
		t.Fatalf("OraConn_Query: get connection fail: %v", err)
	}

	ctx, cancel := oraCon.GetContextWithCallTimeout()
	defer cancel()

	ctxCancelled, cancel := oraCon.GetContextWithCallTimeout()
	cancel()

	type args struct {
		query  string
		params []any
		ctx    context.Context //nolint:containedctx
	}

	type want struct {
		count   int
		wantErr bool
	}

	tests := []struct {
		name string
		args args
		want want
	}{
		{
			"+withoutParams",
			args{
				fmt.Sprintf("SELECT COUNT(*) FROM dba_users WHERE username = '%s'", testCfg.OraUser),
				nil,
				ctx,
			},
			want{1, false},
		},
		{
			"+withParams",
			args{
				"SELECT COUNT(*) FROM dba_users WHERE username = :1",
				[]any{testCfg.OraUser},
				ctx,
			},
			want{1, false},
		},
		{
			"-withCancelledContext",
			args{
				"",
				nil,
				ctxCancelled,
			},
			want{0, true},
		},
		{
			"-withNoQuery",
			args{
				"",
				nil,
				ctx,
			},
			want{0, true},
		},
		{
			"withParamsWhenNoNeed",
			args{
				fmt.Sprintf("SELECT COUNT(*) FROM dba_users WHERE username = '%s'", testCfg.OraUser),
				[]any{"some param"},
				ctx,
			},
			want{0, true},
		},
	}

	for _, tt := range tests { //nolint:paralleltest
		t.Run(tt.name, func(t *testing.T) {
			rows, err := oraCon.Query(tt.args.ctx, tt.args.query, tt.args.params...) //nolint:sqlclosecheck
			defer closeRows(t, rows)

			if err != nil || rows == nil {
				if tt.want.wantErr {
					return
				}

				t.Fatalf("OraConn.Query(): query failed = %v", err)
			}

			if !rows.Next() {
				t.Errorf("OraConn.Query(): no records")

				return
			}

			var count int
			if err := rows.Scan(&count); err != nil {
				t.Errorf("OraConn.Query(): query failed = %v", err)
			}

			if err := rows.Err(); err != nil {
				log.Fatalf("OraConn.Query(): query failed = %v", err)
			}

			if diff := cmp.Diff(tt.want.count, count); diff != "" {
				t.Errorf("OraConn.Query(): records count mismatch = %s", diff)
			}
		})
	}
}

func TestOraConn_QueryByName(t *testing.T) { //nolint:tparallel,gocyclo,cyclop
	t.Parallel()

	opt := dbconn.Options{
		KeepAlive:            100 * time.Second,
		ConnectTimeout:       100 * time.Second,
		CallTimeout:          100 * time.Second,
		CustomQueriesEnabled: true,
		CustomQueriesPath:    t.TempDir(),
	}

	err := seedWithSQL(opt.CustomQueriesPath)
	if err != nil {
		t.Fatalf("OraConn_Query: failed to write file: %v", err)
	}

	connMgr := dbconn.NewConnManager(&mock.MockLogger{}, &opt)
	defer connMgr.Destroy()

	oraCon, err := connMgr.GetConnection(newConnDetDefault(t, testCfg))
	if err != nil {
		t.Fatalf("OraConn_Query: get connection fail: %v", err)
	}

	ctx, cancel := oraCon.GetContextWithCallTimeout()
	defer cancel()

	type args struct {
		queryName string
	}

	type want struct {
		wantErr bool
	}

	tests := []struct {
		name string
		args args
		want want
	}{
		{
			"+good",
			args{"good"},
			want{false},
		},
		{
			"+semicol",
			args{"semicol"},
			want{false},
		},
		{
			"+semiceolBreakspace",
			args{"semicol_breakspace"},
			want{false},
		},
		{
			"+breakspace",
			args{"breakspace"},
			want{false},
		},
		{
			"-onlySemicolBreakspace",
			args{"only_semicol_breakspace"},
			want{true},
		},
		{
			"-unexistentQueryName",
			args{"some_name"},
			want{true},
		},
	}

	for _, tt := range tests { //nolint:paralleltest
		t.Run(tt.name, func(t *testing.T) {
			rows, err := oraCon.QueryByName(ctx, tt.args.queryName) //nolint:sqlclosecheck
			defer closeRows(t, rows)

			if err != nil && !tt.want.wantErr {
				t.Errorf("OraConn.Query(): query failed = %v", err)
			}

			if rows == nil && !tt.want.wantErr {
				t.Errorf("OraConn.Query(): query failed = %v", err)
			}

			if rows != nil && rows.Err() != nil && !tt.want.wantErr {
				t.Errorf("OraConn.Query(): query failed = %v", err)
			}
		})
	}
}

func TestOraConn_QueryRow(t *testing.T) { //nolint:tparallel
	t.Parallel()

	opt := dbconn.Options{
		KeepAlive:            100 * time.Second,
		ConnectTimeout:       100 * time.Second,
		CallTimeout:          100 * time.Second,
		CustomQueriesEnabled: false,
		CustomQueriesPath:    "",
	}

	connMgr := dbconn.NewConnManager(&mock.MockLogger{}, &opt)
	defer connMgr.Destroy()

	oraCon, err := connMgr.GetConnection(newConnDetDefault(t, testCfg))
	if err != nil {
		t.Fatalf("TestConnManager_Query: get connection fail: %v", err)
	}

	ctx, cancel := oraCon.GetContextWithCallTimeout()
	defer cancel()

	ctxCancelled, cancel := oraCon.GetContextWithCallTimeout()
	cancel()

	type args struct {
		query  string
		params []any
		ctx    context.Context //nolint:containedctx
	}

	type want struct {
		wantErr bool
	}

	tests := []struct {
		name string
		args args
		want want
	}{
		{
			"+withoutParams",
			args{
				"SELECT username FROM dba_users",
				nil,
				ctx,
			},
			want{false},
		},
		{
			"+withParams",
			args{
				"SELECT username FROM dba_users WHERE username = :1",
				[]any{testCfg.OraUser},
				ctx,
			},
			want{false},
		},
		{
			"-withCancelledContext",
			args{
				"",
				nil,
				ctxCancelled,
			},
			want{true},
		},
	}

	for _, tt := range tests { //nolint:paralleltest
		t.Run(tt.name, func(t *testing.T) {
			row, err := oraCon.QueryRow(tt.args.ctx, tt.args.query, tt.args.params...)
			if err != nil || row == nil {
				if !tt.want.wantErr {
					t.Errorf("OraConn.Query(): query failed = %v", err)
				}

				return
			}

			var username string
			if err := row.Scan(&username); err != nil {
				if !tt.want.wantErr {
					t.Errorf("OraConn.Query(): query failed = %v", err)
				}
			}
		})
	}
}

func TestOraConn_QueryRowByName(t *testing.T) { //nolint:tparallel
	t.Parallel()

	opt := dbconn.Options{
		KeepAlive:            100 * time.Second,
		ConnectTimeout:       100 * time.Second,
		CallTimeout:          100 * time.Second,
		CustomQueriesEnabled: true,
		CustomQueriesPath:    t.TempDir(),
	}

	err := seedWithSQL(opt.CustomQueriesPath)
	if err != nil {
		t.Fatalf("OraConn_QueryRow: failed to write file: %v", err)
	}

	connMgr := dbconn.NewConnManager(&mock.MockLogger{}, &opt)
	defer connMgr.Destroy()

	oraCon, err := connMgr.GetConnection(newConnDetDefault(t, testCfg))
	if err != nil {
		t.Fatalf("OraConn_Query: get connection fail: %v", err)
	}

	ctx, cancel := oraCon.GetContextWithCallTimeout()
	defer cancel()

	type args struct {
		queryName string
	}

	type want struct {
		wantErr bool
	}

	tests := []struct {
		name string
		args args
		want want
	}{
		{
			"+good",
			args{"good"},
			want{false},
		},
		{
			"+semicol",
			args{"semicol"},
			want{false},
		},
		{
			"+semiceolBreakspace",
			args{"semicol_breakspace"},
			want{false},
		},
		{
			"+breakspace",
			args{"breakspace"},
			want{false},
		},
		{
			"-onlySemicolBreakspace",
			args{"only_semicol_breakspace"},
			want{true},
		},
		{
			"-unexistentQueryNane",
			args{"some_name"},
			want{true},
		},
	}

	for _, tt := range tests { //nolint:paralleltest
		t.Run(tt.name, func(t *testing.T) {
			row, err := oraCon.QueryRowByName(ctx, tt.args.queryName)

			if (err != nil || row == nil) && !tt.want.wantErr {
				t.Errorf("OraConn.QueryRowByName(): query failed = %v", err)
			} else {
				return
			}
		})
	}
}

func seedWithSQL(customQueriesPath string) error {
	var sqls = []struct {
		fileName string
		content  string
	}{
		{
			"good.sql",
			fmt.Sprintf("SELECT COUNT(*) FROM dba_users WHERE username = '%s'", testCfg.OraUser),
		},
		{
			"semicol.sql",
			fmt.Sprintf("SELECT COUNT(*) FROM dba_users WHERE username = '%s';;", testCfg.OraUser),
		},
		{
			"semicol_breakspace.sql",
			fmt.Sprintf("   SELECT COUNT(*) FROM dba_users WHERE username = '%s';;   ", testCfg.OraUser),
		},
		{
			"breakspace.sql",
			fmt.Sprintf("   SELECT COUNT(*) FROM dba_users WHERE username = '%s'   ", testCfg.OraUser),
		},
		{
			"only_semicol_breakspace.sql",
			"   ;;   ",
		},
	}

	for _, v := range sqls {
		filePath := filepath.Join(customQueriesPath, v.fileName)

		err := os.WriteFile(filePath, []byte(v.content), 0600)
		if err != nil {
			return err
		}
	}

	return nil
}

// newConnDet function constructs ConnDetails with default values for testing.
func newConnDetDefault(t *testing.T, c *testConfig) dbconn.ConnDetails {
	t.Helper()

	u, _ := uri.NewWithCreds(
		c.OraIP+"?service="+c.OraSrv,
		c.OraUser,
		c.OraPwd,
		dbconn.URIDefaults)

	return dbconn.ConnDetails{
		Uri:          *u,
		Privilege:    c.OraPrivilege,
		OnlyHostname: false}
}

// closeRows function closes rows if exits. In case of problem - returns an error to subtest.
func closeRows(t *testing.T, rows *sql.Rows) {
	t.Helper()

	if rows != nil {
		err := rows.Close()

		if err != nil {
			t.Errorf("rows.Close() error: %v", err)
		}
	}
}
