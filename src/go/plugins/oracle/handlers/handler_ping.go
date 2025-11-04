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
	"fmt"

	"golang.zabbix.com/agent2/plugins/oracle/dbconn"
)

const (
	// PingFailed means that server is not reachable. this value is returned on eny error.
	PingFailed = 0
	// PingOk means that server is reachable.
	PingOk = 1
)

// PingHandler runs 'SELECT 1 FROM DUAL' and returns PingOk if a connection is alive or PingFailed otherwise.
func PingHandler(ctx context.Context, conn dbconn.OraClient, _ map[string]string, _ ...string) (any, error) {
	var res int

	row, err := conn.QueryRow(ctx, fmt.Sprintf("SELECT %d FROM DUAL", PingOk))
	if err != nil {
		return PingFailed, nil //nolint:nilerr
	}

	err = row.Scan(&res)

	if err != nil || res != PingOk {
		return PingFailed, nil //nolint:nilerr
	}

	return PingOk, nil
}
