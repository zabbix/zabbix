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

func databasesDiscoveryHandler(ctx context.Context, conn OraClient, params map[string]string,
	_ ...string) (interface{}, error) {
	var lld string

	row, err := conn.QueryRow(ctx, `
		SELECT
			JSON_ARRAYAGG(
				JSON_OBJECT(
					'{#DBNAME}' VALUE NAME, 
					'{#TYPE}'   VALUE DECODE(CDB, 'YES', 'CDB', 'No-CDB')
				)
			) LLD
		FROM
			V$DATABASE
	`)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&lld)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	if lld == "" {
		lld = "[]"
	}

	return lld, nil
}
