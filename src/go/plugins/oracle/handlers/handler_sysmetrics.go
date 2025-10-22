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
	"strings"

	"golang.zabbix.com/agent2/plugins/oracle/dbconn"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	duration60sec = "2"
	duration15sec = "3"
)

// SysMetricsHandler function works with system metric values.
func SysMetricsHandler(ctx context.Context, conn dbconn.OraClient, params map[string]string,
	_ ...string) (any, error) {
	var (
		sysMetrics string
		groupID    string
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
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	err = row.Scan(&sysMetrics)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	// Add leading zeros for floats: ".03" -> "0.03".
	// Oracle JSON functions are not RFC 4627 compliant.
	// There should be a better way to do that, but I haven't come up with it ¯\_(ツ)_/¯
	sysMetrics = strings.ReplaceAll(sysMetrics, "\":.", "\":0.")

	return sysMetrics, nil
}
