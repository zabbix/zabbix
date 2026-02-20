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

	netDevFilepath     string
	netDevStatsCount   int
	sysClassNetDirpath string
}

type networkDirection uint8

type msgIfDiscovery struct {
	Ifname string  `json:"{#IFNAME}"`           //nolint:tagliatelle // legacy compatibility
	Ifguid *string `json:"{#IFGUID},omitempty"` //nolint:tagliatelle // legacy compatibility
}

type ifConfigData struct {
	Name      string `json:"name"`
	Alias     string `json:"ifalias"`
	Mac       string `json:"mac"`
	Type      string `json:"type"`
	Speed     uint64 `json:"speed"`
	Duplex    string `json:"duplex"`
	AdmState  string `json:"administrative_state"`
	OperState string `json:"operational_state"`
	Carrier   uint64 `json:"carrier"`
}

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
	Name           string `json:"name"`
	Alias          string `json:"ifalias"`
	Mac            string `json:"mac"`
	Carrier        uint64 `json:"carrier"`
	CarrierChanges uint64 `json:"carrier_changes"`
	CarrierUpCnt   uint64 `json:"carrier_up_count"`
	CarrierDnCnt   uint64 `json:"carrier_down_count"`

	StatsIn  ifStatsIn  `json:"in"`
	StatsOut ifStatsOut `json:"out"`
}

type netIfResult struct {
	Config []ifConfigData `json:"config"`
	Values []IfValuesData `json:"values"`
}
