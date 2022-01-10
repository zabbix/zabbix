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
	"strings"

	"zabbix.com/pkg/zbxerr"
)

const (
	duration60sec = "2"
	duration15sec = "3"
)

func sysMetricsHandler(ctx context.Context, conn OraClient, params map[string]string,
	_ ...string) (interface{}, error) {
	var (
		sysmetrics string
		groupID    = duration60sec
	)

	switch params["Duration"] {
	case "15":
		groupID = duration15sec
	case "60":
		groupID = duration60sec
	default:
		return nil, zbxerr.ErrorInvalidParams
	}

	row, err := conn.QueryRow(ctx, `
		SELECT
			JSON_OBJECTAGG(METRIC_NAME VALUE ROUND(VALUE, 3) RETURNING CLOB)
		FROM
			V$SYSMETRIC
		WHERE
			GROUP_ID = :1
	`, groupID)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = row.Scan(&sysmetrics)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	// Add leading zeros for floats: ".03" -> "0.03".
	// Oracle JSON functions are not RFC 4627 compliant.
	// There should be a better way to do that, but I haven't come up with it ¯\_(ツ)_/¯
	sysmetrics = strings.ReplaceAll(sysmetrics, "\":.", "\":0.")

	return sysmetrics, nil
}
