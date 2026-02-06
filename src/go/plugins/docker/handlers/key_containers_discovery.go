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

//nolint:tagliatelle // our non-standard naming conventions.
type containerDiscovery struct {
	ID   string `json:"{#ID}"`
	Name string `json:"{#NAME}"`
}

func keyContainersDiscovery(client *http.Client, query string, _ ...string) (string, error) {
	var data []*container

	body, err := queryDockerAPI(client, query)
	if err != nil {
		return "", err
	}

	err = json.Unmarshal(body, &data)
	if err != nil {
		return "", errs.WrapConst(err, zbxerr.ErrorCannotUnmarshalJSON)
	}

	result, err := getContainersDiscovery(data)
	if err != nil {
		return "", err
	}

	return string(result), nil
}

func getContainersDiscovery(data []*container) ([]byte, error) {
	containers := make([]containerDiscovery, 0)

	for _, container := range data {
		if len(container.Names) == 0 {
			continue
		}

		containers = append(containers, containerDiscovery{ID: container.ID, Name: container.Names[0]})
	}

	result, err := json.Marshal(&containers)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	return result, nil
}
