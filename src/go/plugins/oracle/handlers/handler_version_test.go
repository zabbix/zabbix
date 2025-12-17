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

package handlers

import (
	"context"
	"database/sql"
	"errors"
	"testing"

	"github.com/DATA-DOG/go-sqlmock"
	"github.com/google/go-cmp/cmp"
	"golang.zabbix.com/agent2/plugins/oracle/dbconn"
)

var _ dbconn.OraClient = (*mockOraClient)(nil)

type mockOraClient struct {
	dbconn.OraClient

	db  *sql.DB
	err error
}

func (m *mockOraClient) QueryRow(
	ctx context.Context, query string, args ...any,
) (*sql.Row, error) {
	if m.err != nil {
		return nil, m.err
	}

	return m.db.QueryRowContext(ctx, query, args...), nil
}

func Test_versionHandler(t *testing.T) {
	t.Parallel()

	type db struct {
		resp   any
		err    error
		rowErr error
	}

	tests := []struct {
		name    string
		db      db
		want    any
		wantErr bool
	}{
		{
			"+valid",
			db{resp: "1.2.3"},
			`1.2.3`,
			false,
		},
		{
			"-queryErr",
			db{resp: "1.2.3", err: errors.New("fail")},
			nil,
			true,
		},
		{
			"-scanErr",
			db{resp: nil},
			nil,
			true,
		},
		{
			"-rowErr",
			db{resp: "1.2.3", rowErr: errors.New("fail")},
			nil,
			true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			db, m, err := sqlmock.New()
			if err != nil {
				t.Fatalf("failed to create sql mock %s", err.Error())
			}

			mockClient := &mockOraClient{
				db: db,
			}

			m.ExpectQuery(`SELECT VERSION_FULL FROM V\$INSTANCE`).
				WillReturnRows(
					sqlmock.NewRows([]string{"VERSION_FULL"}).
						AddRow(tt.db.resp).
						RowError(0, tt.db.rowErr),
				).
				WillReturnError(tt.db.err)

			got, err := VersionHandler(context.Background(), mockClient, nil)
			if (err != nil) != tt.wantErr {
				t.Fatalf(
					"VersionHandler() error = %v, wantErr %v", err, tt.wantErr,
				)
			}

			if diff := cmp.Diff(tt.want, got); diff != "" {
				t.Fatalf("VersionHandler() = %s", diff)
			}

			if err := m.ExpectationsWereMet(); err != nil {
				t.Fatalf("query expectations were not met: %s", err)
			}
		})
	}
}
