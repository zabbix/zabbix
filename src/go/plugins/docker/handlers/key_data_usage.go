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

import (
	"encoding/json"
	"net/http"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

// diskUsage contains response of Engine API:
// GET "/system/df".
//
//nolint:tagliatelle // Docker API uses non-standard naming conventions
type diskUsage struct {
	LayersSize int64        `json:"LayersSize"`
	Images     []*image     `json:"Images"`
	Containers []*container `json:"Containers"`
	Volumes    []*volume    `json:"Volumes"`
}

// volume details.
//
//nolint:tagliatelle // Docker API uses non-standard naming conventions
type volume struct {
	CreatedAt  unixTime         `json:"CreatedAt"`
	Mountpoint string           `json:"Mountpoint"`
	Name       string           `json:"Name"`
	UsageData  *volumeUsageData `json:"UsageData"`
}

// volumeUsageData Usage details about the volume.
//
//nolint:tagliatelle // Docker API uses non-standard naming conventions
type volumeUsageData struct {
	RefCount int64 `json:"RefCount"`
	Size     int64 `json:"Size"`
}

func keyDataUsageHandler(client *http.Client, query string, _ ...string) (string, error) {
	var data diskUsage

	body, err := queryDockerAPI(client, query)
	if err != nil {
		return "", err
	}

	err = json.Unmarshal(body, &data)
	if err != nil {
		return "", errs.WrapConst(err, zbxerr.ErrorCannotUnmarshalJSON)
	}

	result, err := json.Marshal(data)
	if err != nil {
		return "", errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	return string(result), nil
}
