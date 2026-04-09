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

// image contains response of Engine API:
// GET "/images/{name:.*}/json".
//
//nolint:tagliatelle // Docker API uses non-standard naming conventions
type image struct {
	ID          string   `json:"Id"`
	RepoTags    []string `json:"RepoTags"`
	RepoDigests []string `json:"RepoDigests"`
	Containers  int64    `json:"Containers"`
	Created     int64    `json:"Created"`
	Size        int64    `json:"Size"`
	VirtualSize int64    `json:"VirtualSize"`
	SharedSize  int64    `json:"SharedSize"`
}

func keyImagesHandler(client *http.Client, query string, _ ...string) (string, error) {
	var data []image

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
