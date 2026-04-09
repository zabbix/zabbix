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

// Metrics that are working with docker plugin.
const (
	KeyContainerInfo       DockerKey = "docker.container_info"
	KeyContainerStats      DockerKey = "docker.container_stats"
	KeyContainers          DockerKey = "docker.containers"
	KeyContainersDiscovery DockerKey = "docker.containers.discovery"
	KeyDataUsage           DockerKey = "docker.data_usage"
	KeyImages              DockerKey = "docker.images"
	KeyImagesDiscovery     DockerKey = "docker.images.discovery"
	KeyInfo                DockerKey = "docker.info"
	KeyPing                DockerKey = "docker.ping"
)

// DockerKey is metric that this plugin implements.
type DockerKey string
