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

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

func replicationSlaveStatusHandler(
	ctx context.Context,
	conn MyClient,
	params map[string]string,
	_ ...string,
) (any, error) {
	data, err := querySlaveOrReplicaStatus(ctx, conn)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	if len(data) == 0 {
		return nil, errs.Wrap(zbxerr.ErrorEmptyResult, "replication is not configured")
	}

	rule := substituteRulesNew2Old
	if isOldSyle(data) {
		rule = substituteRulesOld2New
	}

	if params[masterHostParam] != "" {
		for _, m := range data {
			if m[masterKey] == params[masterHostParam] || m[sourceKey] == params[masterHostParam] {
				return parseResponse(duplicate(m, rule))
			}
		}

		return nil, errs.Wrapf(zbxerr.ErrorEmptyResult, "master host `%s` not found", params[masterHostParam])
	}

	// If no any host passed in the key's parameter, returns status records of all hosts.
	return parseResponse(duplicateAll(data, rule))
}

func parseResponse(data any) (any, error) {
	jsonRes, err := json.Marshal(data)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	return string(jsonRes), nil
}
