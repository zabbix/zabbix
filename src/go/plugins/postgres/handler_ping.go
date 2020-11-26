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
	"fmt"

	"github.com/jackc/pgx/v4"
	"zabbix.com/pkg/zbxerr"
)

const (
	keyPostgresPing     = "pgsql.ping"
	postgresPingUnknown = -1
	postgresPingFailed  = 0
	postgresPingOk      = 1
)

// pingHandler executes 'SELECT 1 as pingOk' commands and
// returns pingOK if a connection is alive or postgresPingFailed otherwise.
func (p *Plugin) pingHandler(ctx context.Context, conn PostgresClient, key string, params []string) (interface{}, error) {
	var pingOK int64 = postgresPingUnknown

	var row pgx.Row

	row, err := conn.QueryRow(ctx, fmt.Sprintf("SELECT %d as pingOk", postgresPingOk))
	_ = row.Scan(&pingOK)

	if pingOK != postgresPingOk {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	return pingOK, nil
}
