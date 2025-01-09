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

func sysParamsHandler(ctx context.Context, conn OraClient, params map[string]string, _ ...string) (interface{}, error) {
	var sysparams string

	row, err := conn.QueryRow(ctx, `
		SELECT
			JSON_OBJECTAGG(v.NAME VALUE v.VALUE)
		FROM
			V$SYSTEM_PARAMETER v
		WHERE
			NAME IN ('sessions', 'processes', 'db_files')
	`)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&sysparams)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	return sysparams, nil
}
