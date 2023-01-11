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

package netif

import (
	"zabbix.com/pkg/plugin"
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
