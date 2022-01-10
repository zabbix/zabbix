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

package memcached

import (
	"zabbix.com/pkg/metric"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/uri"
)

// handlerFunc defines an interface must be implemented by handlers.
type handlerFunc func(conn MCClient, params map[string]string) (res interface{}, err error)

// getHandlerFunc returns a handlerFunc related to a given key.
func getHandlerFunc(key string) handlerFunc {
	switch key {
	case keyStats:
		return statsHandler // memcached.stats[[connString][,user][,password][,type]]

	case keyPing:
		return pingHandler // memcached.ping[[connString][,user][,password]]

	default:
		return nil
	}
}

const (
	keyPing  = "memcached.ping"
	keyStats = "memcached.stats"
)

// https://github.com/memcached/memcached/blob/master/sasl_defs.c#L26
var maxEntryLen = 252
var uriDefaults = &uri.Defaults{Scheme: "tcp", Port: "11211"}

// Common params: [URI|Session][,User][,Password]
var (
	paramURI = metric.NewConnParam("URI", "URI to connect or session name.").
			WithDefault(uriDefaults.Scheme + "://localhost:" + uriDefaults.Port).WithSession().
			WithValidator(uri.URIValidator{Defaults: uriDefaults, AllowedSchemes: []string{"tcp", "unix"}})
	paramUser     = metric.NewConnParam("User", "Memcached user.").WithDefault("")
	paramPassword = metric.NewConnParam("Password", "User's password.").WithDefault("")
)

var metrics = metric.MetricSet{
	keyPing: metric.New("Test if connection is alive or not.",
		[]*metric.Param{paramURI, paramUser, paramPassword}, false),

	keyStats: metric.New("Returns output of stats command.",
		[]*metric.Param{paramURI, paramUser, paramPassword,
			metric.NewParam("Type", "One of supported stat types: items, sizes, slabs and settings. "+
				"Empty by default (returns general statistics).").WithDefault(statsTypeGeneral).
				WithValidator(metric.SetValidator{
					Set:             []string{statsTypeGeneral, statsTypeItems, statsTypeSizes, statsTypeSlabs, statsTypeSettings},
					CaseInsensitive: true,
				}),
		}, false),
}

func init() {
	plugin.RegisterMetrics(&impl, pluginName, metrics.List()...)
}
