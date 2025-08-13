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

package ceph

import (
	"golang.zabbix.com/agent2/plugins/ceph/handlers"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/metric"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/uri"
)

var uriDefaults = &uri.Defaults{Scheme: "https", Port: "8003"} //nolint:gochecknoglobals // constant.

// Common params: [URI|session][,User][,ApiKey].
//
//nolint:gochecknoglobals // constants.
var (
	paramURI = metric.NewConnParam("URI", "URI to connect or session name.").
			WithDefault(uriDefaults.Scheme + "://localhost:" + uriDefaults.Port).WithSession().
			WithValidator(uri.URIValidator{Defaults: uriDefaults, AllowedSchemes: []string{"https"}})
	paramUsername = metric.NewConnParam("User", "Ceph API user.").SetRequired()
	paramAPIKey   = metric.NewConnParam("APIKey", "Ceph API key.").SetRequired()
	paramMode     = metric.NewConnParam("Mode", "Ceph modes native|restful").
			WithDefault("restful").
			WithValidator(metric.SetValidator{Set: []string{"native", "restful"}, CaseInsensitive: true})
)

//nolint:gochecknoglobals // map that is used as a constant static map.
var metrics = metric.MetricSet{
	string(handlers.KeyDf): metric.New("Returns information about cluster’s data usage and distribution among pools.",
		[]*metric.Param{paramURI, paramUsername, paramAPIKey, paramMode}, false),

	string(handlers.KeyOSD): metric.New("Returns aggregated and per OSD statistics.",
		[]*metric.Param{paramURI, paramUsername, paramAPIKey, paramMode}, false),

	string(handlers.KeyOSDDiscovery): metric.New("Returns a list of discovered OSDs.",
		[]*metric.Param{paramURI, paramUsername, paramAPIKey, paramMode}, false),

	string(handlers.KeyOSDDump): metric.New("Returns usage thresholds and statuses of OSDs.",
		[]*metric.Param{paramURI, paramUsername, paramAPIKey, paramMode}, false),

	string(handlers.KeyPing): metric.New("Tests if a connection is alive or not.",
		[]*metric.Param{paramURI, paramUsername, paramAPIKey, paramMode}, false),

	string(handlers.KeyPoolDiscovery): metric.New("Returns a list of discovered pools.",
		[]*metric.Param{paramURI, paramUsername, paramAPIKey, paramMode}, false),

	string(handlers.KeyStatus): metric.New("Returns an overall cluster's status.",
		[]*metric.Param{paramURI, paramUsername, paramAPIKey, paramMode}, false),
}

//nolint:gochecknoinits // flagship (legacy) implementation
func init() {
	err := plugin.RegisterMetrics(&impl, pluginName, metrics.List()...)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}
