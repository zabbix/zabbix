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
	"net/http"
)

//nolint:gochecknoglobals // constant map.
var handlers = map[DockerKey]Handler{
	KeyInfo:                keyInfoHandler,
	KeyContainers:          keyContainersHandler,
	KeyContainersDiscovery: keyContainersDiscovery,
	KeyImages:              keyImagesHandler,
	KeyImagesDiscovery:     keyImagesDiscoveryHandler,
	KeyDataUsage:           keyDataUsageHandler,
	KeyPing:                keyPingHandler,
	KeyContainerStats:      keyContainerStatsHandler,
	KeyContainerInfo:       keyContainerInfoHandler,
}

// Handler function is a function that handles one of the docker keys.
type Handler func(client *http.Client, query string, args ...string) (result string, err error)

// GetDockerHandler returns appropriate handler based on key, if key not present - nil.
func GetDockerHandler(key DockerKey) Handler {
	handler, ok := handlers[key]
	if !ok {
		return nil
	}

	return handler
}
