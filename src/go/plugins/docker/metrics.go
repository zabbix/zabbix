/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
	"zabbix.com/pkg/metric"
	"zabbix.com/pkg/plugin"
)

type metricMeta struct {
	path string
}

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
	keyContainerInfo: metric.New("Return low-level information about a container.",
		[]*metric.Param{paramContainer}, false),
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

func init() {
	plugin.RegisterMetrics(&impl, pluginName, metrics.List()...)
}
