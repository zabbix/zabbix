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
	"fmt"
)

const (
	pingFailed = 0
	pingOk     = 1
)

// pingHandler queries 'SELECT 1 FROM DUAL' and returns pingOk if a connection is alive or pingFailed otherwise.
func pingHandler(ctx context.Context, conn OraClient, params map[string]string, _ ...string) (interface{}, error) {
	var res int

	row, err := conn.QueryRow(ctx, fmt.Sprintf("SELECT %d FROM DUAL", pingOk))
	if err != nil {
		return pingFailed, nil
	}

	err = row.Scan(&res)

	if err != nil || res != pingOk {
		return pingFailed, nil
	}

	return pingOk, nil
}
