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

	"zabbix.com/pkg/zbxerr"
)

const keySysMetrics = "oracle.sys.metrics"
const sysMetricsMaxParams = 1

const (
	duration60sec = "2"
	duration15sec = "3"
)

func sysMetricsHandler(ctx context.Context, conn OraClient, params []string) (interface{}, error) {
	var (
		sysmetrics string
		groupID    = duration60sec
	)

	if len(params) > sysMetricsMaxParams {
		return nil, zbxerr.ErrorTooManyParameters
	}

	if len(params) == 1 {
		switch params[0] {
		case "15":
			groupID = duration15sec
		case "60":
			groupID = duration60sec
		default:
			return nil, zbxerr.ErrorInvalidParams
		}
	}

	row, err := conn.Query(ctx, `
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

	return sysmetrics, nil
}
