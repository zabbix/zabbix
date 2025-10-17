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
	"database/sql"
	"errors"

	"golang.zabbix.com/agent2/plugins/oracle/dbconn"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

// UserHandler function works with user information.
func UserHandler(ctx context.Context, conn dbconn.OraClient, params map[string]string, _ ...string) (any, error) {
	var userinfo string

	username := conn.WhoAmI()
	if params["Username"] != "" {
		username = params["Username"]
	}

	//nolint:lll
	row, err := conn.QueryRow(ctx, `
		SELECT
			JSON_OBJECT('exp_passwd_days_before' VALUE 
				ROUND(DECODE(SIGN(NVL(EXPIRY_DATE, SYSDATE + 999) - SYSDATE), -1, 0, NVL(EXPIRY_DATE, SYSDATE + 999) - SYSDATE))
			)
		FROM
			DBA_USERS	
		WHERE 
			USERNAME = UPPER(:1)
	`, username)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	err = row.Scan(&userinfo)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, errs.WrapConst(err, zbxerr.ErrorEmptyResult)
		}

		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	return userinfo, nil
}
