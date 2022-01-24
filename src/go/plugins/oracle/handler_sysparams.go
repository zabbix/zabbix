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

package oracle

import (
	"context"

	"zabbix.com/pkg/zbxerr"
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
