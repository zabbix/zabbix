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

package mysql

import (
	"context"
	"encoding/json"

	"golang.zabbix.com/sdk/zbxerr"
)

func replicationDiscoveryHandler(ctx context.Context, conn MyClient, _ map[string]string,
	_ ...string) (interface{}, error) {
	res := make([]map[string]string, 0)

	rows, err := conn.Query(ctx, `SHOW SLAVE STATUS`)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	data, err := rows2data(rows)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	for _, row := range data {
		res = append(res, map[string]string{"Master_Host": row["Master_Host"]})
	}

	jsonRes, err := json.Marshal(res)
	if err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	return string(jsonRes), nil
}
