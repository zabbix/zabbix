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

	netDevFilepath   string
	netDevStatsCount int
}

type networkDirection uint8

type msgIfDiscovery struct {
	Ifname string  `json:"{#IFNAME}"`           //nolint:tagliatelle // legacy compatibility
	Ifguid *string `json:"{#IFGUID},omitempty"` //nolint:tagliatelle // legacy compatibility
}

type ifConfigData struct {
	Ifname      string  `json:"name"`
	Ifalias     string  `json:"ifalias"`
	Ifmac       string  `json:"mac"`
	Iftype      *uint64 `json:"type"`
	Ifspeed     *uint64 `json:"speed"`
	Ifduplex    string  `json:"duplex"`
	IfAdmState  *string `json:"administrative_state"`
	IfOperState *string `json:"operational_state"`
}

// // IfStatistics aggregates incoming and outgoing network interface traffic data.
// type IfStatistics struct {
// 	Ifbytes      *uint64 `json:"bytes,omitempty"`
// 	Ifpackets    *uint64 `json:"packets,omitempty"`
// 	Iferrors     *uint64 `json:"errors,omitempty"`
// 	Ifdropped    *uint64 `json:"dropped,omitempty"`
// 	Ifoverrruns  *uint64 `json:"overruns,omitempty"`
// 	Ifframe      *uint64 `json:"frame,omitempty"`
// 	Ifcompressed *uint64 `json:"compressed,omitempty"`
// 	Ifmulticast  *uint64 `json:"multicast,omitempty"`
// 	Ifcollisions *uint64 `json:"collisions,omitempty"`
// 	Ifcarrier    *uint64 `json:"carrier,omitempty"`
// }

type ifStatsIn struct {
	Bytes      uint64 `json:"bytes"`
	Packets    uint64 `json:"packets"`
	Err        uint64 `json:"errors"`
	Drop       uint64 `json:"dropped"`
	Fifo       uint64 `json:"overruns"`
	Frame      uint64 `json:"frame"`
	Compressed uint64 `json:"compressed"`
	Multicast  uint64 `json:"multicast"`
}
type ifStatsOut struct {
	Bytes      uint64 `json:"bytes"`
	Packets    uint64 `json:"packets"`
	Err        uint64 `json:"errors"`
	Drop       uint64 `json:"dropped"`
	Colls      uint64 `json:"collisions"`
	Fifo       uint64 `json:"overruns"`
	Carrier    uint64 `json:"carrier"`
	Compressed uint64 `json:"compressed"`
}

// IfValuesData contains the combined configuration and statistical values for an interface.
type IfValuesData struct {
	Ifname    string     `json:"name"`
	Ifalias   string     `json:"ifalias"`
	Ifmac     string     `json:"mac"`
	Iftype    *uint64    `json:"type"`
	Ifcarrier *uint64    `json:"carrier"`
	StatsIn   ifStatsIn  `json:"in"`
	StatsOut  ifStatsOut `json:"out"`
}

type netIfResult struct {
	Config []ifConfigData `json:"config"`
	Values []IfValuesData `json:"values"`
}
