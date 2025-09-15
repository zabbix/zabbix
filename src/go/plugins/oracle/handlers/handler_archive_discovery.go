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

// ArchiveDiscoveryHandler function works with an archive logs list.
func ArchiveDiscoveryHandler(ctx context.Context, conn dbconn.OraClient, _ map[string]string,
	_ ...string) (any, error) {
	var lld string

	row, err := conn.QueryRow(ctx, `
		SELECT
			JSON_ARRAYAGG(
				JSON_OBJECT(
					'{#DEST_NAME}' VALUE d.DEST_NAME
				) RETURNING CLOB 
			) LLD
		FROM
			V$ARCHIVE_DEST d,
			V$DATABASE db
		WHERE
			d.STATUS != 'INACTIVE'
			AND db.LOG_MODE = 'ARCHIVELOG'
	`)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	err = row.Scan(&lld)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	if lld == "" {
		lld = "[]"
	}

	return lld, nil
}
