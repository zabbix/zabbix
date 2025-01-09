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
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/metric"
	"golang.zabbix.com/sdk/plugin"
)

const (
	keyContainerInfo       = "docker.container_info"
	keyContainerStats      = "docker.container_stats"
	keyContainers          = "docker.containers"
	keyContainersDiscovery = "docker.containers.discovery"
	keyDataUsage           = "docker.data_usage"
	keyImages              = "docker.images"
	keyImagesDiscovery     = "docker.images.discovery"
	keyInfo                = "docker.info"
	keyPing                = "docker.ping"
)

var metricsMeta = map[string]metricMeta{
	keyContainerInfo:       {"containers/%s/json"},
	keyContainerStats:      {"containers/%s/stats?stream=false"},
	keyContainers:          {"containers/json?all=true"},
	keyContainersDiscovery: {"containers/json?all=%s"},
	keyDataUsage:           {"system/df"},
	keyImages:              {"images/json"},
	keyImagesDiscovery:     {"images/json"},
	keyInfo:                {"info"},
	keyPing:                {"_ping"},
}

var (
	paramContainer = metric.NewParam("Container", "Container name for which the information is needed.").
			SetRequired()
	paramStatusAll = metric.NewParam("All", "Return all containers (true) or only running (false).").
			WithDefault("false").
			WithValidator(metric.SetValidator{Set: []string{"true", "false"}, CaseInsensitive: true})
)

var metrics = metric.MetricSet{
	keyContainerInfo: metric.New(
		"Return low-level information about a container.",
		[]*metric.Param{
			paramContainer,
			metric.NewParam("Info", "Return all JSON fields (full) or partial JSON fields (short).").
				WithDefault("short").
				WithValidator(metric.SetValidator{Set: []string{"full", "short"}, CaseInsensitive: true}),
		},
		false),
	keyContainerStats: metric.New("Returns near realtime stats for a given container.",
		[]*metric.Param{paramContainer}, false),
	keyContainers: metric.New("Returns a list of containers.", nil, false),
	keyContainersDiscovery: metric.New("Returns a list of containers, used for low-level discovery.",
		[]*metric.Param{paramStatusAll}, false),
	keyDataUsage: metric.New("Returns information about current data usage.", nil, false),
	keyImages:    metric.New("Returns a list of images.", nil, false),
	keyImagesDiscovery: metric.New("Returns a list of images, used for low-level discovery.",
		nil, false),
	keyInfo: metric.New("Returns information about the docker server.", nil, false),
	keyPing: metric.New("Pings the server and returns 0 or 1.", nil, false),
}

type metricMeta struct {
	path string
}

func init() {
	err := plugin.RegisterMetrics(&impl, pluginName, metrics.List()...)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}
