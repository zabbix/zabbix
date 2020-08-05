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
)

const keyArchive = "oracle.archive.info"

const archiveMaxParams = 0

// archiveHandler TODO: add description.
func archiveHandler(ctx context.Context, conn OraClient, params []string) (interface{}, error) {
	var archiveLogs string

	if len(params) > archiveMaxParams {
		return nil, errorTooManyParameters
	}

	row, err := conn.QueryRow(ctx, `
		SELECT
			JSON_ARRAYAGG(
				JSON_OBJECT(d.DEST_NAME VALUE
					JSON_OBJECT(
						'status'       VALUE DECODE(d.STATUS, 'VALID', 3, 'DEFERRED', 2, 'ERROR', 1, 0),
						'log_sequence' VALUE d.LOG_SEQUENCE,
						'error'        VALUE NVL(TO_CHAR(d.ERROR), ' ')
					)
				)
			)		
		FROM
			V$ARCHIVE_DEST d,
			V$DATABASE db
		WHERE 
			d.STATUS != 'INACTIVE' 
			AND db.LOG_MODE = 'ARCHIVELOG'
	`)
	if err != nil {
		return nil, fmt.Errorf("%w (%s)", errorCannotFetchData, err.Error())
	}

	err = row.Scan(&archiveLogs)
	if err != nil {
		return nil, fmt.Errorf("%w (%s)", errorCannotParseData, err.Error())
	}

	if archiveLogs == "" {
		archiveLogs = "[]"
	}

	return archiveLogs, nil
}
