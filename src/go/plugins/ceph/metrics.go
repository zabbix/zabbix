/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

package ceph

import (
	"zabbix.com/pkg/plugin"
)

// handlerFunc defines an interface must be implemented by handlers.
type handlerFunc func(data []byte) (res interface{}, err error)

type metricParams struct {
	description string
	cmd         string
	params      map[string]string
	handler     handlerFunc
}

var (
	extraParamDetails = map[string]string{"details": "details"}
	//extraParamOsd     = map[string]string{"dumpcontents": "osds"}
)

// Handle TODO.
func (mp *metricParams) Handle(data []byte) (res interface{}, err error) {
	return mp.handler(data)
}

type pluginMetrics map[string]metricParams

var metrics = pluginMetrics{
	keyDf: metricParams{
		description: "TODO.",
		cmd:         "df",
		params:      extraParamDetails,
		handler:     dfHandler,
	},
	keyOSD: metricParams{
		description: "TODO.",
		cmd:         "pg dump",
		params:      nil,
		handler:     OSDHandler,
	},
	keyOSDDiscovery: metricParams{
		description: "TODO.",
		cmd:         "osd ls",
		params:      nil,
		handler:     OSDDiscoveryHandler,
	},
	keyOSDDump: metricParams{
		description: "TODO.",
		cmd:         "osd dump",
		params:      nil,
		handler:     OSDDumpHandler,
	},
	keyPing: metricParams{
		description: "TODO.",
		cmd:         "health",
		params:      nil,
		handler:     pingHandler,
	},
	keyPoolDiscovery: metricParams{
		description: "TODO.",
		cmd:         "osd pool ls",
		params:      nil,
		handler:     poolDiscoveryHandler,
	},
	keyStatus: metricParams{
		description: "Returns status of cluster.",
		cmd:         "status",
		params:      nil,
		handler:     statusHandler,
	},
}

const (
	keyDf            = "ceph.df.details"
	keyOSD           = "ceph.osd.stats"
	keyOSDDiscovery  = "ceph.osd.discovery"
	keyOSDDump       = "ceph.osd.dump"
	keyPing          = "ceph.ping"
	keyPoolDiscovery = "ceph.pool.discovery"
	keyStatus        = "ceph.status"
)

// Metrics TODO
func (pm pluginMetrics) Metrics() (res []string) {
	for key, params := range pm {
		res = append(res, key, params.description)
	}

	return
}

func init() {
	plugin.RegisterMetrics(&impl, pluginName, metrics.Metrics()...)
}
