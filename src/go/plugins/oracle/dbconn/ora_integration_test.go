//go:build oracle_tests

/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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

package dbconn

import (
	"context"
	"log"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"
)

var sqls = []struct { //nolint:gochecknoglobals
	fileName string
	content  string
}{
	{
		"good.sql",
		"SELECT COUNT(*) FROM dba_users WHERE username = 'ZABBIX_MON'",
	},
	{
		"semicol.sql",
		"SELECT COUNT(*) FROM dba_users WHERE username = 'ZABBIX_MON';;",
	},
	{
		"semicol_breakspace.sql",
		"   SELECT COUNT(*) FROM dba_users WHERE username = 'ZABBIX_MON';;   ",
	},
	{
		"breakspace.sql",
		"   SELECT COUNT(*) FROM dba_users WHERE username = 'ZABBIX_MON'   ",
	},
	{
		"only_semicol_breakspace.sql",
		"   ;;   ",
	},
}

func TestOraConn_Query(t *testing.T) { //nolint:tparallel
	t.Parallel()

	opt := Options{
		KeepAlive:            100 * time.Second,
		ConnectTimeout:       100 * time.Second,
		CallTimeout:          100 * time.Second,
		CustomQueriesEnabled: false,
		CustomQueriesPath:    "",
	}

	connMgr := NewConnManager(&mockLogger{}, opt)
	defer connMgr.Destroy()

	oraCon, err := connMgr.GetConnection(newConnDet(t, "zabbix_mon", ""))
	if err != nil {
		t.Fatalf("OraConn_Query: get connection fail: %v", err)
	}

	ctx, cancel := oraCon.GetContextWithTimeout()
	defer cancel()

	ctxCancelled, cancel := oraCon.GetContextWithTimeout()
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
				"SELECT COUNT(*) FROM dba_users WHERE username = 'ZABBIX_MON'",
				nil,
				ctx,
			},
			want{1, false},
		},
		{
			"+withParams",
			args{
				"SELECT COUNT(*) FROM dba_users WHERE username = :1",
				[]any{"ZABBIX_MON"},
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
				"SELECT COUNT(*) FROM dba_users WHERE username = 'ZABBIX_MON'",
				[]any{"some param"},
				ctx,
			},
			want{0, true},
		},
	}

	for _, tt := range tests { //nolint:paralleltest
		t.Run(tt.name, func(t *testing.T) {
			rows, err := oraCon.Query(tt.args.ctx, tt.args.query, tt.args.params...) //nolint:sqlclosecheck
			defer CloseRows(t, rows)

			if err != nil || rows == nil {
				if tt.want.wantErr {
					return
				}

				t.Fatalf("OraConn.Query(): querry failed = %v", err)
			}

			if !rows.Next() {
				t.Errorf("OraConn.Query(): no records")

				return
			}

			var count int
			if err := rows.Scan(&count); err != nil {
				t.Errorf("OraConn.Query(): querry failed = %v", err)
			}

			if err := rows.Err(); err != nil {
				log.Fatalf("OraConn.Query(): querry failed = %v", err)
			}

			if diff := cmp.Diff(tt.want.count, count); diff != "" {
				t.Errorf("OraConn.Query(): records count mismatch = %s", diff)
			}
		})
	}
}

func TestOraConn_QueryByName(t *testing.T) { //nolint:tparallel,gocyclo,cyclop
	t.Parallel()

	opt := Options{
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

	connMgr := NewConnManager(&mockLogger{}, opt)
	defer connMgr.Destroy()

	oraCon, err := connMgr.GetConnection(newConnDet(t, "zabbix_mon", ""))
	if err != nil {
		t.Fatalf("OraConn_Query: get connection fail: %v", err)
	}

	ctx, cancel := oraCon.GetContextWithTimeout()
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
			defer CloseRows(t, rows)

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

	opt := Options{
		KeepAlive:            100 * time.Second,
		ConnectTimeout:       100 * time.Second,
		CallTimeout:          100 * time.Second,
		CustomQueriesEnabled: false,
		CustomQueriesPath:    "",
	}

	connMgr := NewConnManager(&mockLogger{}, opt)
	defer connMgr.Destroy()

	oraCon, err := connMgr.GetConnection(newConnDet(t, "zabbix_mon", ""))
	if err != nil {
		t.Fatalf("TestConnManager_Query: get connection fail: %v", err)
	}

	ctx, cancel := oraCon.GetContextWithTimeout()
	defer cancel()

	ctxCancelled, cancel := oraCon.GetContextWithTimeout()
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
				[]any{"ZABBIX_MON"},
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

	opt := Options{
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

	connMgr := NewConnManager(&mockLogger{}, opt)
	defer connMgr.Destroy()

	oraCon, err := connMgr.GetConnection(newConnDet(t, "zabbix_mon", ""))
	if err != nil {
		t.Fatalf("OraConn_Query: get connection fail: %v", err)
	}

	ctx, cancel := oraCon.GetContextWithTimeout()
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
	for _, v := range sqls {
		filePath := filepath.Join(customQueriesPath, v.fileName)

		err := os.WriteFile(filePath, []byte(v.content), 0600)
		if err != nil {
			return err
		}
	}

	return nil
}
