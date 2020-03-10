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
	keyPostgresBgwriter = "pgsql.bgwriter"
)

// bgwriterHandler executes select  with statistics from pg_stat_bgwriter and returns JSON if all is OK or nil otherwise.
func (p *Plugin) bgwriterHandler(conn *postgresConn, key string, params []string) (interface{}, error) {
	var bgwriterJSON string
	query := `
  SELECT row_to_json (T)
    FROM (
          SELECT
              checkpoints_timed
            , checkpoints_req
            , checkpoint_write_time
            , checkpoint_sync_time
            , buffers_checkpoint
            , buffers_clean
            , maxwritten_clean
            , buffers_backend
            , buffers_backend_fsync
            , buffers_alloc
          FROM pg_catalog.pg_stat_bgwriter
          ) T ;`
	err := conn.postgresPool.QueryRow(context.Background(), query).Scan(&bgwriterJSON)
	if err != nil {
		if err == pgx.ErrNoRows {
			p.Errf(err.Error())
			return nil, errorEmptyResult
		}
		p.Errf(err.Error())
		return nil, errorCannotFetchData
	}

	return bgwriterJSON, nil
}
