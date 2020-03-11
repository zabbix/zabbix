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

const (
	errorCannotFetchData         = "Cannot fetch data."
	errorCannotReadResponse      = "Cannot read response."
	errorCannotUnmarshalJSON     = "Cannot unmarshal JSON."
	errorCannotUnmarshalAPIError = "Cannot unmarshal API error."
	errorCannotMarshalJSON       = "Cannot marshal JSON."
	errorTooManyParams           = "Too many parameters."
	errorUnsupportedMetric       = "Unsupported metric."
	errorParametersNotAllowed    = "Item does not allow parameters."
	errorInvalidEndpoint         = "Invalid endpoint format."
	errorQueryErrorMessage       = "Docker returned an error."
)

type key struct {
	name      string
	path      string
	numParams int
}

var (
	keyInfo           = key{"docker.info", "info", 0}
	keyContainers     = key{"docker.containers", "containers/json?all=true", 0}
	keyImages         = key{"docker.images", "images/json", 0}
	keyDataUsage      = key{"docker.data_usage", "system/df", 0}
	keyContainerInfo  = key{"docker.container_info", "containers/%s/json", 1}
	keyContainerStats = key{"docker.container_stats", "containers/%s/stats?stream=false", 1}
	keyPing           = key{"docker.ping", "_ping", 0}
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

func (cli *client) Query(params []string, key *key) ([]byte, error) {
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
		if err != nil {
			return nil, err
		}

		if string(body) == "OK" {
			return 1, nil
		}
		return 0, nil

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
		keyImages.name, "Returns a list of images.",
		keyDataUsage.name, "Returns information about current data usage.",
		keyContainerInfo.name, "Return low-level information about a container.",
		keyContainerStats.name, "Returns near realtime stats for a given container.",
		keyPing.name, "Pings the server and returns 0 or 1.")
}
