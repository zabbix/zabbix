/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

// archiveHandler gets info about count and size of archive files and returns JSON if all is OK or nil otherwise.
func archiveHandler(ctx context.Context, conn PostgresClient,
	_ string, _ map[string]string, _ ...string) (interface{}, error) {
	var archiveCountJSON, archiveSizeJSON string

	queryArchiveCount := `SELECT row_to_json(T)
							FROM (
									SELECT archived_count, failed_count
								   	  FROM pg_stat_archiver
								) T;`

	queryArchiveSize := `SELECT row_to_json(T)
							FROM (
									SELECT count(name) AS count_files ,
									coalesce(sum((pg_stat_file('./pg_wal/' || rtrim(ready.name,'.ready'))).size),0) AS size_files
									FROM (
										SELECT name
										  FROM pg_ls_dir('./pg_wal/archive_status') name
										  WHERE right( name,6)= '.ready'
										 ) ready
								) T;`

	row, err := conn.QueryRow(ctx, queryArchiveCount)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&archiveCountJSON)
	if err != nil {
		if errors.Is(err, pgx.ErrNoRows) {
			return nil, zbxerr.ErrorEmptyResult.Wrap(err)
		}

		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	row, err = conn.QueryRow(ctx, queryArchiveSize)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&archiveSizeJSON)
	if err != nil {
		if errors.Is(err, pgx.ErrNoRows) {
			return nil, zbxerr.ErrorEmptyResult.Wrap(err)
		}

		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	result := archiveCountJSON[:len(archiveCountJSON)-1] + "," + archiveSizeJSON[1:]

	return result, nil
}
