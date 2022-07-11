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

	"git.zabbix.com/ap/plugin-support/zbxerr"
	"github.com/jackc/pgx/v4"
)

// databasesBloatingHandler gets info about count and size of archive files and returns JSON if all is OK or nil otherwise.
func databasesBloatingHandler(ctx context.Context, conn PostgresClient,
	_ string, _ map[string]string, _ ...string) (interface{}, error) {
	var countBloating int64

	query := `SELECT count(*)
				FROM pg_catalog.pg_stat_all_tables
	   		   WHERE (n_dead_tup/(n_live_tup+n_dead_tup)::float8) > 0.2
		 		 AND (n_live_tup+n_dead_tup) > 50;`

	row, err := conn.QueryRow(ctx, query)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&countBloating)
	if err != nil {
		if errors.Is(err, pgx.ErrNoRows) {
			return nil, zbxerr.ErrorEmptyResult.Wrap(err)
		}

		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	return countBloating, nil
}
