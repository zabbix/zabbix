/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

	"github.com/jackc/pgx/v4"
)

const (
	//keyPostgresCountArchive = "pgsql.archive"
	keyPostgresSizeArchive = "pgsql.archive"
)

// archiveHandler gets info about count and size of archive files and returns JSON if all is OK or nil otherwise.
func (p *Plugin) archiveHandler(conn *postgresConn, key string, params []string) (interface{}, error) {
	var archiveCountJSON, archiveSizeJSON string
	var err error
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

	err = conn.postgresPool.QueryRow(context.Background(), queryArchiveCount).Scan(&archiveCountJSON)
	if err != nil {
		if err == pgx.ErrNoRows {
			p.Errf(err.Error())
			return nil, errorEmptyResult
		}
		p.Errf(err.Error())
		return nil, errorCannotFetchData
	}
	err = conn.postgresPool.QueryRow(context.Background(), queryArchiveSize).Scan(&archiveSizeJSON)
	if err != nil {
		if err == pgx.ErrNoRows {
			p.Errf(err.Error())
			return nil, errorEmptyResult
		}
		p.Errf(err.Error())
		return nil, errorCannotFetchData
	}
	result := string(archiveCountJSON[:len(archiveCountJSON)-1]) + "," + string(archiveSizeJSON[1:])
	return result, nil
}
