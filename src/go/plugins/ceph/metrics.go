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

type command string

// handlerFunc defines an interface must be implemented by handlers.
type handlerFunc func(data map[command][]byte) (res interface{}, err error)

type metric struct {
	description string
	commands    []command
	params      map[string]string
	handler     handlerFunc
}

var (
	extraParamDetails = map[string]string{"detail": "detail"}
)

// handle runs metric's handler.
func (m *metric) handle(data map[command][]byte) (res interface{}, err error) {
	return m.handler(data)
}

type pluginMetrics map[string]metric

const (
	keyDf            = "ceph.df.details"
	keyOSD           = "ceph.osd.stats"
	keyOSDDiscovery  = "ceph.osd.discovery"
	keyOSDDump       = "ceph.osd.dump"
	keyPing          = "ceph.ping"
	keyPoolDiscovery = "ceph.pool.discovery"
	keyStatus        = "ceph.status"
)

const (
	cmdDf               = "df"
	cmdPgDump           = "pg dump"
	cmdOSDCrushRuleDump = "osd crush rule dump"
	cmdOSDCrushTree     = "osd crush tree"
	cmdOSDDump          = "osd dump"
	cmdHealth           = "health"
	cmdStatus           = "status"
)

var metrics = pluginMetrics{
	keyDf: metric{
		description: "Returns information about cluster’s data usage and distribution among pools.",
		commands:    []command{cmdDf},
		params:      extraParamDetails,
		handler:     dfHandler,
	},
	keyOSD: metric{
		description: "Returns aggregated and per OSD statistics.",
		commands:    []command{cmdPgDump},
		params:      nil,
		handler:     osdHandler,
	},
	keyOSDDiscovery: metric{
		description: "Returns a list of discovered OSDs.",
		commands:    []command{cmdOSDCrushRuleDump, cmdOSDCrushTree},
		params:      nil,
		handler:     osdDiscoveryHandler,
	},
	keyOSDDump: metric{
		description: "Returns usage thresholds and statuses of OSDs.",
		commands:    []command{cmdOSDDump},
		params:      nil,
		handler:     osdDumpHandler,
	},
	keyPing: metric{
		description: "Tests if a connection is alive or not.",
		commands:    []command{cmdHealth},
		params:      nil,
		handler:     pingHandler,
	},
	keyPoolDiscovery: metric{
		description: "Returns a list of discovered pools.",
		commands:    []command{cmdOSDDump, cmdOSDCrushRuleDump},
		params:      nil,
		handler:     poolDiscoveryHandler,
	},
	keyStatus: metric{
		description: "Returns an overall cluster's status.",
		commands:    []command{cmdStatus},
		params:      nil,
		handler:     statusHandler,
	},
}

// metrics returns an array of metrics and their descriptions suitable to pass to plugin.RegisterMetrics.
func (pm pluginMetrics) metrics() (res []string) {
	for key, params := range pm {
		res = append(res, key, params.description)
	}

	return
}

func init() {
	plugin.RegisterMetrics(&impl, pluginName, metrics.metrics()...)
}
