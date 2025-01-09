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

	"golang.zabbix.com/sdk/zbxerr"
)

func procHandler(ctx context.Context, conn OraClient, params map[string]string, _ ...string) (interface{}, error) {
	var proc string

	row, err := conn.QueryRow(ctx, `
		SELECT
			JSON_OBJECT('proc_num' VALUE COUNT(*))
		FROM
			V$PROCESS
	`)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&proc)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	return proc, nil
}
