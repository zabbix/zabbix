/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

	"git.zabbix.com/ap/plugin-support/zbxerr"
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
