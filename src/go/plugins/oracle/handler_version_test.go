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
	"errors"
	"testing"

	"github.com/DATA-DOG/go-sqlmock"
	"github.com/google/go-cmp/cmp"
)

var _ OraClient = (*mockOraClient)(nil)

type mockOraClient struct {
	OraClient
	db  *sql.DB
	err error
}

func (m *mockOraClient) QueryRow(
	ctx context.Context, query string, args ...interface{},
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
		want    interface{}
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
		tt := tt
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

			got, err := versionHandler(context.Background(), mockClient, nil)
			if (err != nil) != tt.wantErr {
				t.Fatalf(
					"versionHandler() error = %v, wantErr %v", err, tt.wantErr,
				)
			}

			if diff := cmp.Diff(tt.want, got); diff != "" {
				t.Fatalf("versionHandler() = %s", diff)
			}

			if err := m.ExpectationsWereMet(); err != nil {
				t.Fatalf("query expectations were not met: %s", err)
			}
		})
	}
}
