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

package oracle

import (
	"context"

	"golang.zabbix.com/sdk/zbxerr"
)

// versionHandler queries the db server for version.
func versionHandler(
	ctx context.Context, conn OraClient, _ map[string]string, _ ...string,
) (any, error) {
	const query = `SELECT VERSION_FULL FROM V$INSTANCE`

	row, err := conn.QueryRow(ctx, query)
	if err != nil {
		return nil, zbxerr.New("failed to query version").Wrap(err)
	}

	var version string

	err = row.Scan(&version)
	if err != nil {
		return nil, zbxerr.New("failed scan version").Wrap(err)
	}

	err = row.Err()
	if err != nil {
		return nil, zbxerr.New("failed to get version").Wrap(err)
	}

	return version, nil
}
