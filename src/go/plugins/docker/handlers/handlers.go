/*
** Zabbix
** Copyright (C) 2001-2026 Zabbix SIA
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
