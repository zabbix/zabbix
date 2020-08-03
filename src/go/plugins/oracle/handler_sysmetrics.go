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
	"encoding/json"
	"fmt"
)

const keySysMetrics = "oracle.sys.metrics"
const sysMetricsMaxParams = 1

const (
	delta60sec = "2"
	delta15sec = "3"
)

// sysMetricsHandler TODO: add description.
func sysMetricsHandler(ctx context.Context, conn OraClient, params []string) (interface{}, error) {
	var groupId = delta60sec

	if len(params) > sysMetricsMaxParams {
		return nil, errorTooManyParameters
	}

	if len(params) == 1 {
		switch params[0] {
		case "15":
			groupId = delta15sec
		case "60":
			groupId = delta60sec
		default:
			return nil, errorInvalidParams
		}
	}

	rows, err := conn.Query(ctx, `
		SELECT
			METRIC_NAME AS METRIC,
			VALUE
		FROM
			V$SYSMETRIC
		WHERE
			GROUP_ID = :1
	`, groupId)
	if err != nil {
		return nil, fmt.Errorf("%w (%s)", errorCannotFetchData, err.Error())
	}

	var metric, value string
	res := make(map[string]string)

	for rows.Next() {
		err = rows.Scan(&metric, &value)
		if err != nil {
			return nil, fmt.Errorf("%w (%s)", errorCannotParseData, err.Error())
		}
		res[metric] = value
	}

	jsonRes, err := json.Marshal(res)
	if err != nil {
		return nil, fmt.Errorf("%w (%s)", errorCannotMarshalJSON, err.Error())
	}

	return string(jsonRes), nil
}
