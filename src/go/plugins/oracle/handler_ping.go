/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

package oracle

import (
	"context"
	"fmt"

	"zabbix.com/pkg/zbxerr"
)

const (
	pingFailed = 0
	pingOk     = 1
)

const keyPing = "oracle.ping"

const pingMaxParams = 0

// pingHandler queries 'SELECT 1 FROM DUAL' and returns pingOk if a connection is alive or pingFailed otherwise.
func pingHandler(ctx context.Context, conn OraClient, params []string) (interface{}, error) {
	var res int

	if len(params) > pingMaxParams {
		return nil, zbxerr.ErrorTooManyParameters
	}

	row, err := conn.QueryRow(ctx, fmt.Sprintf("SELECT %d FROM DUAL", pingOk))
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&res)

	if err != nil || res != pingOk {
		return pingFailed, nil
	}

	return pingOk, nil
}
