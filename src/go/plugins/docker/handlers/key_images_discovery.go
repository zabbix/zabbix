/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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

package handlers

import (
	"encoding/json"
	"net/http"

	"golang.zabbix.com/sdk/zbxerr"
)

type imageDiscovery struct {
	ID   string `json:"{#ID}"`
	Name string `json:"{#NAME}"`
}

func keyImagesDiscoveryHandler(client *http.Client, query string, _ ...string) (string, error) {
	var data []Image

	body, err := queryDockerAPI(client, query)
	if err != nil {
		return "", err
	}

	if err = json.Unmarshal(body, &data); err != nil {
		return "", zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
	}

	result, err := getImagesDiscovery(data)
	if err != nil {
		return "", err
	}

	return string(result), nil
}

func getImagesDiscovery(data []Image) (result []byte, err error) {
	images := make([]imageDiscovery, 0)

	for _, image := range data {
		if len(image.RepoTags) == 0 {
			continue
		}

		images = append(images, imageDiscovery{ID: image.ID, Name: image.RepoTags[0]})
	}

	if result, err = json.Marshal(&images); err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	return
}
