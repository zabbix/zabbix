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

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

func replicationDiscoveryHandler(
	ctx context.Context,
	conn MyClient,
	_ map[string]string,
	_ ...string,
) (any, error) {
	data, err := querySlaveOrReplicaStatus(ctx, conn)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	var discovered map[string]string

	res := make([]map[string]string, 0)
	isOldStyleKeys := isOldSyle(data)

	// Always returns both key names — 'Source' and 'Master'.
	for _, row := range data {
		switch isOldStyleKeys {
		case true:
			discovered = duplicate(map[string]string{masterKey: row[masterKey]}, substituteRulesOld2New)
		case false:
			discovered = duplicate(map[string]string{sourceKey: row[sourceKey]}, substituteRulesNew2Old)
		}

		res = append(res, discovered)
	}

	return parseResponse(res)
}
