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

package redis

import (
	"zabbix.com/pkg/metric"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/uri"
)

// handlerFunc defines an interface must be implemented by handlers.
type handlerFunc func(conn redisClient, params map[string]string) (res interface{}, err error)

// getHandlerFunc returns a handlerFunc related to a given key.
func getHandlerFunc(key string) handlerFunc {
	switch key {
	case keyConfig:
		return configHandler

	case keyInfo:
		return infoHandler

	case keyPing:
		return pingHandler

	case keySlowlog:
		return slowlogHandler

	default:
		return nil
	}
}

const (
	keyConfig  = "redis.config"
	keyInfo    = "redis.info"
	keyPing    = "redis.ping"
	keySlowlog = "redis.slowlog.count"
)

var maxAuthPassLen = 512
var uriDefaults = &uri.Defaults{Scheme: "tcp", Port: "6379"}

// Common params: [URI|Session][,User][,Password]
var (
	paramURI = metric.NewConnParam("URI", "URI to connect or session name.").
			WithDefault(uriDefaults.Scheme + "://localhost:" + uriDefaults.Port).WithSession().
			WithValidator(uri.URIValidator{Defaults: uriDefaults, AllowedSchemes: []string{"tcp", "unix"}})
	paramPassword = metric.NewConnParam("Password", "Redis password.").WithDefault("").
			WithValidator(metric.LenValidator{Max: &maxAuthPassLen})
)

var metrics = metric.MetricSet{
	keyConfig: metric.New("Returns configuration parameters of Redis server.",
		[]*metric.Param{paramURI, paramPassword,
			metric.NewParam("Pattern", "Glob-style pattern to filter configuration parameters.").
				WithDefault("*"),
		}, false),

	keyInfo: metric.New("Returns output of INFO command.",
		[]*metric.Param{paramURI, paramPassword,
			metric.NewParam("Section", "Section of information to return.").WithDefault("default"),
		}, false),

	keyPing: metric.New("Test if connection is alive or not.",
		[]*metric.Param{paramURI, paramPassword}, false),

	keySlowlog: metric.New("Returns the number of slow log entries since Redis has been started.",
		[]*metric.Param{paramURI, paramPassword}, false),
}

func init() {
	plugin.RegisterMetrics(&impl, pluginName, metrics.List()...)
}
