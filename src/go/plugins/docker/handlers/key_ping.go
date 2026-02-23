/*
** Copyright (C) 2001-2026 Zabbix SIA
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

import "net/http"

const (
	pingFailed = "0"
	pingOk     = "1"
)

func keyPingHandler(client *http.Client, query string, _ ...string) (string, error) {
	body, err := queryDockerAPI(client, query)
	if err != nil || string(body) != "OK" {
		return pingFailed, nil //nolint:nilerr // is intended behavior.
	}

	return pingOk, nil
}
