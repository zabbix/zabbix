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

package docker

import (
	"encoding/json"

	"golang.zabbix.com/sdk/zbxerr"
)

type containerDiscovery struct {
	ID   string `json:"{#ID}"`
	Name string `json:"{#NAME}"`
}

type imageDiscovery struct {
	ID   string `json:"{#ID}"`
	Name string `json:"{#NAME}"`
}

func (p *Plugin) getContainersDiscovery(data []Container) (result []byte, err error) {
	containers := make([]containerDiscovery, 0)

	for _, container := range data {
		if len(container.Names) == 0 {
			continue
		}

		containers = append(containers, containerDiscovery{ID: container.ID, Name: container.Names[0]})
	}

	if result, err = json.Marshal(&containers); err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	return
}

func (p *Plugin) getImagesDiscovery(data []Image) (result []byte, err error) {
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
