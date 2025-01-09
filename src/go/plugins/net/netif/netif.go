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

package netif

import (
	"golang.zabbix.com/sdk/plugin"
)

const (
	errorInvalidSecondParam   = "Invalid second parameter."
	errorEmptyIfName          = "Network interface name cannot be empty."
	errorTooManyParams        = "Too many parameters."
	errorUnsupportedMetric    = "Unsupported metric."
	errorParametersNotAllowed = "Item does not allow parameters."
)

// Plugin -
type Plugin struct {
	plugin.Base
}

var impl Plugin

type dirFlag uint8

const (
	dirIn dirFlag = 1 << iota
	dirOut
)

type msgIfDiscovery struct {
	Ifname string  `json:"{#IFNAME}"`
	Ifguid *string `json:"{#IFGUID},omitempty"`
}
