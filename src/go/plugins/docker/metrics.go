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

package docker

import (
	"golang.zabbix.com/agent2/plugins/docker/handlers"
	"golang.zabbix.com/sdk/metric"
)

//nolint:gochecknoglobals // used as constant map.
var metricsMeta = map[handlers.DockerKey]metricMeta{
	handlers.KeyContainerInfo:       {"containers/%s/json"},
	handlers.KeyContainerStats:      {"containers/%s/stats?stream=false"},
	handlers.KeyContainers:          {"containers/json?all=true"},
	handlers.KeyContainersDiscovery: {"containers/json?all=%s"},
	handlers.KeyDataUsage:           {"system/df"},
	handlers.KeyImages:              {"images/json"},
	handlers.KeyImagesDiscovery:     {"images/json"},
	handlers.KeyInfo:                {"info"},
	handlers.KeyPing:                {"_ping"},
}

//nolint:gochecknoglobals //used as constants.
var (
	paramContainer = metric.NewParam("Container", "Container name for which the information is needed.").
			SetRequired()
	paramStatusAll = metric.NewParam("All", "Return all containers (true) or only running (false).").
			WithDefault("false").
			WithValidator(metric.SetValidator{Set: []string{"true", "false"}, CaseInsensitive: true})
)

//nolint:gochecknoglobals // used as constant.
var metrics = metric.MetricSet{
	string(handlers.KeyContainerInfo): metric.New(
		"Return low-level information about a container.",
		[]*metric.Param{
			paramContainer,
			metric.NewParam("Info", "Return all JSON fields (full) or partial JSON fields (short).").
				WithDefault("short").
				WithValidator(metric.SetValidator{Set: []string{"full", "short"}, CaseInsensitive: true}),
		},
		false),
	string(handlers.KeyContainerStats): metric.New("Returns near realtime stats for a given container.",
		[]*metric.Param{paramContainer}, false),
	string(handlers.KeyContainers): metric.New("Returns a list of containers.", nil, false),
	string(handlers.KeyContainersDiscovery): metric.New("Returns a list of containers, used for low-level discovery.",
		[]*metric.Param{paramStatusAll}, false),
	string(handlers.KeyDataUsage): metric.New("Returns information about current data usage.", nil, false),
	string(handlers.KeyImages):    metric.New("Returns a list of images.", nil, false),
	string(handlers.KeyImagesDiscovery): metric.New("Returns a list of images, used for low-level discovery.",
		nil, false),
	string(handlers.KeyInfo): metric.New("Returns information about the docker server.", nil, false),
	string(handlers.KeyPing): metric.New("Pings the server and returns 0 or 1.", nil, false),
}

type metricMeta struct {
	path string
}
