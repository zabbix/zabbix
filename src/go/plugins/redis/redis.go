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

package redis

import (
	"errors"
	"zabbix.com/pkg/plugin"
)

const pluginName = "Redis"

const (
	keyInfo    = "redis.info"
	keyPing    = "redis.ping"
	keyConfig  = "redis.config"
	keySlowlog = "redis.slowlog.count"
)

// maxParams defines the maximum number of parameters for metrics.
var maxParams = map[string]int{
	keyInfo:    2,
	keyPing:    1,
	keyConfig:  2,
	keySlowlog: 1,
}

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base
	connMgr *connManager
	options PluginOptions
}

type handler func(conn redisClient, params []string) (res interface{}, err error)

// impl is the pointer to the plugin implementation.
var impl Plugin

// Export implements the Exporter interface.
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	var (
		uri     URI
		handler handler
	)

	// The first param can be either a URI or a session identifier.
	if len(params) > 0 && len(params[0]) > 0 {
		if isLooksLikeUri(params[0]) {
			// Use the URI from key
			uri, err = newUriWithCreds(params[0], p.options.Password)
		} else {
			if _, ok := p.options.Sessions[params[0]]; !ok {
				return nil, errorUnknownSession
			}
			// Use a pre-defined session
			uri, err = newUriWithCreds(p.options.Sessions[params[0]].Uri, p.options.Sessions[params[0]].Password)
		}
	} else {
		// Use the default URI if the first param is omitted.
		uri, err = newUriWithCreds(p.options.Uri, p.options.Password)
	}

	if err != nil {
		return nil, err
	}

	switch key {
	case keyInfo:
		handler = p.infoHandler // redis.info[[uri][,section]]

	case keyPing:
		handler = p.pingHandler // redis.ping[[uri]]

	case keyConfig:
		handler = p.configHandler // redis.config[[uri][,pattern]]

	case keySlowlog:
		handler = p.slowlogHandler // redis.slowlog[[uri]]

	default:
		return nil, errorUnsupportedMetric
	}

	if len(params) > maxParams[key] {
		return nil, errorTooManyParameters
	}

	conn, err := p.connMgr.GetConnection(uri)
	if err != nil {
		// Special logic of processing connection errors is used if redis.ping is requested
		// because it must return pingFailed if any error occurred.
		if key == keyPing {
			return pingFailed, nil
		}

		p.Errf(err.Error())
		return nil, errors.New(formatZabbixError(err.Error()))
	}

	return handler(conn, params)
}

// init registers metrics.
func init() {
	plugin.RegisterMetrics(&impl, pluginName,
		keyInfo, "Returns output of INFO command.",
		keyPing, "Test if connection is alive or not.",
		keyConfig, "Returns configuration parameters of Redis server.",
		keySlowlog, "Returns the number of slow log entries since Redis has been started.")
}
