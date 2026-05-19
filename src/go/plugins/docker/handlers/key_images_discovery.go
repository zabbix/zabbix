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
type imageDiscovery struct {
	ID   string `json:"{#ID}"`
	Name string `json:"{#NAME}"`
}

func keyImagesDiscoveryHandler(client *http.Client, query string, _ ...string) (string, error) {
	var data []image

	body, err := queryDockerAPI(client, query)
	if err != nil {
		return "", err
	}

	err = json.Unmarshal(body, &data)
	if err != nil {
		return "", errs.WrapConst(err, zbxerr.ErrorCannotUnmarshalJSON)
	}

	result, err := getImagesDiscovery(data)
	if err != nil {
		return "", err
	}

	return string(result), nil
}

func getImagesDiscovery(data []image) ([]byte, error) {
	images := make([]imageDiscovery, 0)

	for _, image := range data {
		if len(image.RepoTags) == 0 {
			continue
		}

		images = append(images, imageDiscovery{ID: image.ID, Name: image.RepoTags[0]})
	}

	result, err := json.Marshal(&images)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	return result, nil
}
