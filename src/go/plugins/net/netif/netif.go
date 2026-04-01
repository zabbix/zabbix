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

package netif

import (
	"golang.zabbix.com/sdk/plugin"
)

const (
	errorInvalidSecondParam   = "invalid second parameter"
	errorEmptyIfName          = "network interface name cannot be empty"
	errorTooManyParams        = "too many parameters"
	errorUnsupportedMetric    = "unsupported metric"
	errorParametersNotAllowed = "item does not allow parameters"
)

const (
	directionIn networkDirection = iota
	directionOut
	directionTotal
)

// Plugin netif plugin implementation.
type Plugin struct {
	plugin.Base

	netDevFilepath string
}

type networkDirection uint8

type msgIfDiscovery struct {
	Ifname string  `json:"{#IFNAME}"`           //nolint:tagliatelle // legacy compatibility
	Ifguid *string `json:"{#IFGUID},omitempty"` //nolint:tagliatelle // legacy compatibility
}
