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

type ifConfigData struct {
	Ifname      string  `json:"name"`
	Ifmac       string  `json:"mac,omitempty"`
	Ifalias     string  `json:"ifalias,omitempty"`
	IfAdmState  *string `json:"administrative_state"`
	IfOperState *string `json:"operational_state"`
}

type IfStatistics struct {
	Ifbytes      *uint64 `json:"bytes,omitempty""`
	Ifpackets    *uint64 `json:"packets,omitempty""`
	Iferrors     *uint64 `json:"errors,omitempty""`
	Ifdropped    *uint64 `json:"dropped,omitempty""`
	Ifoverrruns  *uint64 `json:"overruns,omitempty""`
	Ifframe      *uint64 `json:"frame,omitempty""`
	Ifcompressed *uint64 `json:"compressed,omitempty""`
	Ifmulticast  *uint64 `json:"multicast,omitempty""`
	Ifcollisions *uint64 `json:"collisions,omitempty""`
	Ifcarrier    *uint64 `json:"carrier,omitempty""`
}

type IfValuesData struct {
	Ifname        string       `json:"name"`
	Ifalias       string       `json:"ifalias,omitempty"`
	In            IfStatistics `json:"in"`
	Out           IfStatistics `json:"out"`
	Ifmac         string       `json:"mac,omitempty"`
	Iftype        *uint64      `json:"type,omitempty"`
	Ifcarrier     *uint64      `json:"carrier,omitempty"`
	Ifnegotiation string       `json:"negotiation,omitempty"`
	Ifduplex      string       `json:"duplex,omitempty"`
	Ifspeed       *uint64      `json:"speed,omitempty"`
	Ifslevel      *int64       `json:"signal_level,omitempty"`
	Iflquality    *int64       `json:"link_quality,omitempty"`
	Ifnoiselevel  *int64       `json:"noise_level,omitempty"`
	Ifssid        *string      `json:"ssid,omitempty"`
	Ifbitrate     *int64       `json:"bitrate,omitempty"`
}

type netIfResult struct {
	Config []ifConfigData `json:"config"`
	Values []IfValuesData `json:"values"`
}
