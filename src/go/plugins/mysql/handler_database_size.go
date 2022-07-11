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

package mysql

import (
	"context"

	"git.zabbix.com/ap/plugin-support/zbxerr"
)

func databaseSizeHandler(ctx context.Context, conn MyClient,
	params map[string]string, _ ...string) (interface{}, error) {
	var res string

	row, err := conn.QueryRow(ctx, `
		SELECT 
			COALESCE(SUM(data_length + index_length), 0) 
		FROM
			information_schema.tables 
		WHERE table_schema=?
	`, params["Database"])
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&res)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	return res, nil
}
