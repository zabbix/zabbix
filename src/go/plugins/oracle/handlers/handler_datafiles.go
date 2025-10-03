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

package handlers

import (
	"context"

	"golang.zabbix.com/agent2/plugins/oracle/dbconn"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

// DataFileHandler function works with data files statistics.
func DataFileHandler(ctx context.Context, conn dbconn.OraClient, _ map[string]string, _ ...string) (any, error) {
	var datafiles string

	row, err := conn.QueryRow(ctx, `
		SELECT
			JSON_OBJECT('datafile_num' VALUE COUNT(*))
		FROM
			V$DATAFILE
	`)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	err = row.Scan(&datafiles)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	return datafiles, nil
}
