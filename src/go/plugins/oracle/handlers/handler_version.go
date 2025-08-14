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

// VersionHandler queries the db server for version.
func VersionHandler(
	ctx context.Context, conn dbconn.OraClient, _ map[string]string, _ ...string,
) (any, error) {
	const query = `SELECT VERSION_FULL FROM V$INSTANCE`

	row, err := conn.QueryRow(ctx, query)
	if err != nil {
		return nil, errs.Wrap(err, "failed to query version")
	}

	var version string

	err = row.Scan(&version)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	err = row.Err()
	if err != nil {
		return nil, errs.Wrap(err, "failed to get version")
	}

	return version, nil
}
