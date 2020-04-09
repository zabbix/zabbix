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

package docker

import (
	"encoding/json"
	"errors"
	"fmt"
	"io/ioutil"
	"net/http"
	"path"

	"zabbix.com/pkg/plugin"
)

const (
	pluginName    = "Docker"
	dockerVersion = "1.28"
)

type containerDiscovery struct {
	ID   string `json:"{#ID}"`
	Name string `json:"{#NAME}"`
}

type imageDiscovery struct {
	ID   string `json:"{#ID}"`
	Name string `json:"{#NAME}"`
}

type itemKey struct {
	name      string
	path      string
	numParams int
}

var (
	keyInfo                = itemKey{"docker.info", "info", 0}
	keyContainers          = itemKey{"docker.containers", "containers/json?all=true", 0}
	keyContainersDiscovery = itemKey{"docker.containers.discovery", "containers/json?all=%s", 1}
	keyImages              = itemKey{"docker.images", "images/json", 0}
	keyImagesDiscovery     = itemKey{"docker.images.discovery", "images/json", 0}
	keyDataUsage           = itemKey{"docker.data_usage", "system/df", 0}
	keyContainerInfo       = itemKey{"docker.container_info", "containers/%s/json", 1}
	keyContainerStats      = itemKey{"docker.container_stats", "containers/%s/stats?stream=false", 1}
	keyPing                = itemKey{"docker.ping", "_ping", 0}
)

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base
	options Options
	client  *client
}

var impl Plugin

func checkParams(params []string, paramNum int) error {
	if paramLen := len(params); paramNum == 0 && paramLen > paramNum {
		return errors.New(errorParametersNotAllowed)
	} else if paramLen != paramNum {
		return errors.New(errorTooManyParams)
	}
	return nil
}

func (cli *client) Query(params []string, key *itemKey) ([]byte, error) {
	keyPath := key.path

	if len(params) > 0 {
		iSlice := make([]interface{}, len(params))
		for i, p := range params {
			iSlice[i] = p
		}
		keyPath = fmt.Sprintf(keyPath, iSlice...)
	}

	resp, err := cli.client.Get("http://" + path.Join(dockerVersion, keyPath))
	if err != nil {
		impl.Debugf("cannot fetch data: %s", err)
		return nil, errors.New(errorCannotFetchData)
	}
	defer resp.Body.Close()

	body, err := ioutil.ReadAll(resp.Body)
	if err != nil {
		return nil, errors.New(errorCannotReadResponse)
	}

	if resp.StatusCode != http.StatusOK {
		var apiErr ErrorMessage
		if err = json.Unmarshal(body, &apiErr); err != nil {
			return nil, errors.New(errorCannotUnmarshalAPIError)
		}
		return nil, errors.New(apiErr.Message)
	}

	return body, nil
}

func (p *Plugin) getContainersDiscovery(params []string, data []Container) (result []byte, err error) {
	containers := make([]containerDiscovery, 0)

	for _, container := range data {
		if len(container.Names) == 0 {
			continue
		}

		containers = append(containers, containerDiscovery{ID: container.ID, Name: container.Names[0]})
	}

	if result, err = json.Marshal(&containers); err != nil {
		return nil, errors.New(errorCannotUnmarshalJSON)
	}

	return
}

func (p *Plugin) getImagesDiscovery(params []string, data []Image) (result []byte, err error) {
	images := make([]imageDiscovery, 0)

	for _, image := range data {
		if len(image.RepoTags) == 0 {
			continue
		}

		images = append(images, imageDiscovery{ID: image.ID, Name: image.RepoTags[0]})
	}

	if result, err = json.Marshal(&images); err != nil {
		return nil, errors.New(errorCannotUnmarshalJSON)
	}

	return
}

// Export implements the Exporter interface.
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (interface{}, error) {
	var result []byte

	switch key {
	case keyInfo.name:
		var data Info
		if err := checkParams(params, keyInfo.numParams); err != nil {
			return nil, err
		}

		body, err := p.client.Query(params, &keyInfo)
		if err != nil {
			return nil, err
		}

		if err = json.Unmarshal(body, &data); err != nil {
			p.Debugf("cannot unmarshal JSON: %s", err)
			return nil, errors.New(errorCannotUnmarshalJSON)
		}

		result, err = json.Marshal(data)
		if err != nil {
			p.Debugf("cannot marshal JSON: %s", err)
			return nil, errors.New(errorCannotMarshalJSON)
		}

	case keyContainers.name:
		var data []Container
		if err := checkParams(params, keyContainers.numParams); err != nil {
			return nil, err
		}

		body, err := p.client.Query(params, &keyContainers)
		if err != nil {
			return nil, err
		}

		if err = json.Unmarshal(body, &data); err != nil {
			p.Debugf("cannot unmarshal JSON: %s", err)
			return nil, errors.New(errorCannotUnmarshalJSON)
		}

		result, err = json.Marshal(data)
		if err != nil {
			p.Debugf("cannot marshal JSON: %s", err)
			return nil, errors.New(errorCannotMarshalJSON)
		}

	case keyContainersDiscovery.name:
		var data []Container
		if err := checkParams(params, keyContainersDiscovery.numParams); err != nil {
			return nil, err
		}

		body, err := p.client.Query(params, &keyContainersDiscovery)
		if err != nil {
			return nil, err
		}

		if err = json.Unmarshal(body, &data); err != nil {
			p.Debugf("cannot unmarshal JSON: %s", err)
			return nil, errors.New(errorCannotUnmarshalJSON)
		}

		result, err = p.getContainersDiscovery(params, data)
		if err != nil {
			p.Debugf("cannot marshal JSON: %s", err)
			return nil, errors.New(errorCannotMarshalJSON)
		}

	case keyImages.name:
		var data []Image
		if err := checkParams(params, keyImages.numParams); err != nil {
			return nil, err
		}

		body, err := p.client.Query(params, &keyImages)
		if err != nil {
			return nil, err
		}

		if err = json.Unmarshal(body, &data); err != nil {
			p.Debugf("cannot unmarshal JSON: %s", err)
			return nil, errors.New(errorCannotUnmarshalJSON)
		}

		result, err = json.Marshal(data)
		if err != nil {
			p.Debugf("cannot marshal JSON: %s", err)
			return nil, errors.New(errorCannotMarshalJSON)
		}

	case keyImagesDiscovery.name:
		var data []Image
		if err := checkParams(params, keyImagesDiscovery.numParams); err != nil {
			return nil, err
		}

		body, err := p.client.Query(params, &keyImagesDiscovery)
		if err != nil {
			return nil, err
		}

		if err = json.Unmarshal(body, &data); err != nil {
			p.Debugf("cannot unmarshal JSON: %s", err)
			return nil, errors.New(errorCannotUnmarshalJSON)
		}

		result, err = p.getImagesDiscovery(params, data)
		if err != nil {
			p.Debugf("cannot marshal JSON: %s", err)
			return nil, errors.New(errorCannotMarshalJSON)
		}

	case keyDataUsage.name:
		var data DiskUsage
		if err := checkParams(params, keyDataUsage.numParams); err != nil {
			return nil, err
		}

		body, err := p.client.Query(params, &keyDataUsage)
		if err != nil {
			return nil, err
		}

		if err = json.Unmarshal(body, &data); err != nil {
			p.Debugf("cannot unmarshal JSON: %s", err)
			return nil, errors.New(errorCannotUnmarshalJSON)
		}

		result, err = json.Marshal(data)
		if err != nil {
			p.Debugf("cannot marshal JSON: %s", err)
			return nil, errors.New(errorCannotMarshalJSON)
		}

	case keyContainerInfo.name:
		var data ContainerInfo
		if err := checkParams(params, keyContainerInfo.numParams); err != nil {
			return nil, err
		}

		body, err := p.client.Query(params, &keyContainerInfo)
		if err != nil {
			return nil, err
		}

		if err = json.Unmarshal(body, &data); err != nil {
			p.Debugf("cannot unmarshal JSON: %s", err)
			return nil, errors.New(errorCannotUnmarshalJSON)
		}

		result, err = json.Marshal(data)
		if err != nil {
			p.Debugf("cannot marshal JSON: %s", err)
			return nil, errors.New(errorCannotMarshalJSON)
		}

	case keyContainerStats.name:
		var data ContainerStats
		if err := checkParams(params, keyContainerStats.numParams); err != nil {
			return nil, err
		}

		body, err := p.client.Query(params, &keyContainerStats)
		if err != nil {
			return nil, err
		}

		if err = json.Unmarshal(body, &data); err != nil {
			p.Debugf("cannot unmarshal JSON: %s", err)
			return nil, errors.New(errorCannotUnmarshalJSON)
		}

		result, err = json.Marshal(data)
		if err != nil {
			p.Debugf("cannot marshal JSON: %s", err)
			return nil, errors.New(errorCannotMarshalJSON)
		}

	case keyPing.name:
		if err := checkParams(params, keyPing.numParams); err != nil {
			return nil, err
		}

		body, err := p.client.Query(params, &keyPing)
		if err != nil || string(body) != "OK" {
			return 0, nil
		}

		return 1, nil

	default:
		return nil, errors.New(errorUnsupportedMetric)
	}

	return string(result), nil
}

// init registers metrics.
func init() {
	plugin.RegisterMetrics(&impl, pluginName,
		keyInfo.name, "Returns information about the docker server.",
		keyContainers.name, "Returns a list of containers.",
		keyContainersDiscovery.name, "Returns a list of containers, used for low-level discovery.",
		keyImages.name, "Returns a list of images.",
		keyImagesDiscovery.name, "Returns a list of images, used for low-level discovery.",
		keyDataUsage.name, "Returns information about current data usage.",
		keyContainerInfo.name, "Return low-level information about a container.",
		keyContainerStats.name, "Returns near realtime stats for a given container.",
		keyPing.name, "Pings the server and returns 0 or 1.")
}
