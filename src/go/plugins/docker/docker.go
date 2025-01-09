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
	"errors"
	"fmt"

	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	pluginName    = "Docker"
	dockerVersion = "1.28"
)

const (
	pingFailed = 0
	pingOk     = 1
)

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base
	options Options
	client  *client
}

var impl Plugin

// Export implements the Exporter interface.
func (p *Plugin) Export(key string, rawParams []string, _ plugin.ContextProvider) (interface{}, error) {
	var result []byte

	params, _, _, err := metrics[key].EvalParams(rawParams, nil)
	if err != nil {
		return nil, err
	}

	queryPath := metricsMeta[key].path

	switch key {
	case keyInfo:
		var data Info

		body, err := p.client.Query(queryPath)
		if err != nil {
			return nil, err
		}

		if err = json.Unmarshal(body, &data); err != nil {
			return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
		}

		result, err = json.Marshal(data)
		if err != nil {
			return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
		}

	case keyContainers:
		var data []Container

		body, err := p.client.Query(queryPath)
		if err != nil {
			return nil, err
		}

		if err = json.Unmarshal(body, &data); err != nil {
			return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
		}

		result, err = json.Marshal(data)
		if err != nil {
			return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
		}

	case keyContainersDiscovery:
		var data []Container

		body, err := p.client.Query(fmt.Sprintf(queryPath, params["All"]))
		if err != nil {
			return nil, err
		}

		if err = json.Unmarshal(body, &data); err != nil {
			return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
		}

		result, err = p.getContainersDiscovery(data)
		if err != nil {
			return nil, err
		}

	case keyImages:
		var data []Image

		body, err := p.client.Query(queryPath)
		if err != nil {
			return nil, err
		}

		if err = json.Unmarshal(body, &data); err != nil {
			return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
		}

		result, err = json.Marshal(data)
		if err != nil {
			return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
		}

	case keyImagesDiscovery:
		var data []Image

		body, err := p.client.Query(queryPath)
		if err != nil {
			return nil, err
		}

		if err = json.Unmarshal(body, &data); err != nil {
			return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
		}

		result, err = p.getImagesDiscovery(data)
		if err != nil {
			return nil, err
		}

	case keyDataUsage:
		var data DiskUsage

		body, err := p.client.Query(queryPath)
		if err != nil {
			return nil, err
		}

		if err = json.Unmarshal(body, &data); err != nil {
			return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
		}

		result, err = json.Marshal(data)
		if err != nil {
			return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
		}

	case keyContainerInfo:
		result, err = p.containerInfo(queryPath, params["Container"], params["Info"])
		if err != nil {
			return nil, err
		}

	case keyContainerStats:
		var data ContainerStats

		body, err := p.client.Query(fmt.Sprintf(queryPath, params["Container"]))
		if err != nil {
			return nil, err
		}

		if err = json.Unmarshal(body, &data); err != nil {
			return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
		}

		data.setCPUPercentUsage()

		result, err = json.Marshal(data)
		if err != nil {
			return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
		}

	case keyPing:
		body, err := p.client.Query(queryPath)
		if err != nil || string(body) != "OK" {
			return pingFailed, nil
		}

		return pingOk, nil
	}

	return string(result), nil
}

func (s *Stats) setCPUPercentUsage() {
	// based on formula from docker api doc.
	delta := s.CPUStats.CPUUsage.TotalUsage - s.PreCPUStats.CPUUsage.TotalUsage
	systemDelta := s.CPUStats.SystemUsage - s.PreCPUStats.SystemUsage
	cpuNum := s.CPUStats.OnlineCPUs
	s.CPUStats.CPUUsage.PercentUsage = (float64(delta) / float64(systemDelta)) * float64(cpuNum) * 100.0
}

func (p *Plugin) containerInfo(queryPath, container, info string) ([]byte, error) {
	body, err := p.client.Query(fmt.Sprintf(queryPath, container))
	if err != nil {
		return nil, err
	}

	switch info {
	case "full":
		return body, nil
	case "short":
		var info ContainerInfo

		if err = json.Unmarshal(body, &info); err != nil {
			return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
		}

		result, err := json.Marshal(info)
		if err != nil {
			return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
		}

		return result, nil
	default:
		return nil, zbxerr.ErrorInvalidParams.Wrap(errors.New("Info must be either 'full' or 'short'"))
	}
}
