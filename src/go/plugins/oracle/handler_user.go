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
	"database/sql"
	"fmt"
)

const keyUser = "oracle.user.info"

const userMaxParams = 1

// UserHandler TODO: add description.
func UserHandler(ctx context.Context, conn OraClient, params []string) (interface{}, error) {
	var userinfo string

	username := conn.WhoAmI()

	if len(params) > userMaxParams {
		return nil, errorTooManyParameters
	}

	if len(params) == 1 {
		username = params[0]
	}

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
		return nil, fmt.Errorf("%w (%s)", errorCannotFetchData, err.Error())
	}

	err = row.Scan(&userinfo)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, fmt.Errorf("%w (%s)", errorEmptyResult, err.Error())
		}

		return nil, fmt.Errorf("%w (%s)", errorCannotParseData, err.Error())
	}

	return userinfo, nil
}
