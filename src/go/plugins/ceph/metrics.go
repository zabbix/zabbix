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

package ceph

import (
	"zabbix.com/pkg/metric"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/uri"
)

type command string

// handlerFunc defines an interface must be implemented by handlers.
type handlerFunc func(data map[command][]byte) (res interface{}, err error)

type metricMeta struct {
	commands []command
	args     map[string]string
	handler  handlerFunc
}

// handle runs metric's handler.
func (m *metricMeta) handle(data map[command][]byte) (res interface{}, err error) {
	return m.handler(data)
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

const (
	cmdDf               = "df"
	cmdPgDump           = "pg dump"
	cmdOSDCrushRuleDump = "osd crush rule dump"
	cmdOSDCrushTree     = "osd crush tree"
	cmdOSDDump          = "osd dump"
	cmdHealth           = "health"
	cmdStatus           = "status"
)

var metricsMeta = map[string]metricMeta{
	keyDf: {
		commands: []command{cmdDf},
		args:     map[string]string{"detail": "detail"},
		handler:  dfHandler,
	},
	keyOSD: {
		commands: []command{cmdPgDump},
		args:     nil,
		handler:  osdHandler,
	},
	keyOSDDiscovery: {
		commands: []command{cmdOSDCrushTree},
		args:     nil,
		handler:  osdDiscoveryHandler,
	},
	keyOSDDump: {
		commands: []command{cmdOSDDump},
		args:     nil,
		handler:  osdDumpHandler,
	},
	keyPing: {
		commands: []command{cmdHealth},
		args:     nil,
		handler:  pingHandler,
	},
	keyPoolDiscovery: {
		commands: []command{cmdOSDDump, cmdOSDCrushRuleDump},
		args:     nil,
		handler:  poolDiscoveryHandler,
	},
	keyStatus: {
		commands: []command{cmdStatus},
		args:     nil,
		handler:  statusHandler,
	},
}

var (
	uriDefaults = &uri.Defaults{Scheme: "https", Port: "8003"}
)

// Common params: [URI|Session][,User][,ApiKey]
var (
	paramURI = metric.NewConnParam("URI", "URI to connect or session name.").
			WithDefault(uriDefaults.Scheme + "://localhost:" + uriDefaults.Port).WithSession().
			WithValidator(uri.URIValidator{Defaults: uriDefaults, AllowedSchemes: []string{"https"}})
	paramUsername = metric.NewConnParam("User", "Ceph API user.").SetRequired()
	paramAPIKey   = metric.NewConnParam("APIKey", "Ceph API key.").SetRequired()
)

var metrics = metric.MetricSet{
	keyDf: metric.New("Returns information about cluster’s data usage and distribution among pools.",
		[]*metric.Param{paramURI, paramUsername, paramAPIKey}, false),

	keyOSD: metric.New("Returns aggregated and per OSD statistics.",
		[]*metric.Param{paramURI, paramUsername, paramAPIKey}, false),

	keyOSDDiscovery: metric.New("Returns a list of discovered OSDs.",
		[]*metric.Param{paramURI, paramUsername, paramAPIKey}, false),

	keyOSDDump: metric.New("Returns usage thresholds and statuses of OSDs.",
		[]*metric.Param{paramURI, paramUsername, paramAPIKey}, false),

	keyPing: metric.New("Tests if a connection is alive or not.",
		[]*metric.Param{paramURI, paramUsername, paramAPIKey}, false),

	keyPoolDiscovery: metric.New("Returns a list of discovered pools.",
		[]*metric.Param{paramURI, paramUsername, paramAPIKey}, false),

	keyStatus: metric.New("Returns an overall cluster's status.",
		[]*metric.Param{paramURI, paramUsername, paramAPIKey}, false),
}

func init() {
	plugin.RegisterMetrics(&impl, pluginName, metrics.List()...)
}
