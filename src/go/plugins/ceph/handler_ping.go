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

package ceph

import (
	"encoding/json"
)

type cephHealth struct {
	Status string `json:"status"`
}

const (
	pingFailed = 0
	pingOk     = 1
)

// pingHandler returns pingOk if a connection is alive or pingFailed otherwise.
func pingHandler(data map[command][]byte) (interface{}, error) {
	var health cephHealth

	err := json.Unmarshal(data[cmdHealth], &health)
	if err != nil {
		return pingFailed, nil
	}

	if len(health.Status) > 0 {
		return pingOk, nil
	}

	return pingFailed, nil
}
