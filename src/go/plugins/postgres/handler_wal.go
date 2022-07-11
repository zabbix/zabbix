/*
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

	"git.zabbix.com/ap/plugin-support/zbxerr"
	"github.com/jackc/pgx/v4"
)

// walHandler executes select from directory which contains wal files and returns JSON if all is OK or nil otherwise.
func walHandler(ctx context.Context, conn PostgresClient,
	_ string, _ map[string]string, _ ...string) (interface{}, error) {
	var walJSON string

	query := `SELECT row_to_json(T)
			    FROM (
					SELECT
						CASE
							WHEN pg_is_in_recovery() THEN 0
							ELSE pg_wal_lsn_diff(pg_current_wal_lsn(),'0/00000000')
						END AS WRITE,
						CASE 
							WHEN NOT pg_is_in_recovery() THEN 0
							ELSE pg_wal_lsn_diff(pg_last_wal_receive_lsn(),'0/00000000')
						END AS RECEIVE,
						count(*)
						FROM pg_ls_waldir() AS COUNT
					) T;`

	row, err := conn.QueryRow(ctx, query)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&walJSON)
	if err != nil {
		if errors.Is(err, pgx.ErrNoRows) {
			return nil, zbxerr.ErrorEmptyResult.Wrap(err)
		}

		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	return walJSON, nil
}
