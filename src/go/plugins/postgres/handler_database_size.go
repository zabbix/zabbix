/* /*
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
	"context"
	"errors"

	"github.com/jackc/pgx/v4"
	"zabbix.com/pkg/zbxerr"
)

// databaseSizeHandler gets info about count and size of archive files and returns JSON if all is OK or nil otherwise.
func databaseSizeHandler(ctx context.Context, conn PostgresClient,
	_ string, params map[string]string, _ ...string) (interface{}, error) {
	var countSize int64

	query := `SELECT pg_database_size(datname::text)
		FROM pg_catalog.pg_database
   		WHERE datistemplate = false
			 AND datname = $1;`

	row, err := conn.QueryRow(ctx, query, params["Database"])
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&countSize)
	if err != nil {
		if errors.Is(err, pgx.ErrNoRows) {
			return nil, zbxerr.ErrorEmptyResult.Wrap(err)
		}

		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	return countSize, nil
}
