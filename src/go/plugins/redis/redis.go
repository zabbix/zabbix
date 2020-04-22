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
	"time"
	"zabbix.com/pkg/plugin"
)

const pluginName = "Redis"

const (
	keyInfo    = "redis.info"
	keyPing    = "redis.ping"
	keyConfig  = "redis.config"
	keySlowlog = "redis.slowlog.count"
)

const commonParamsNum = 2

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base
	connMgr *ConnManager
	options PluginOptions
}

// handlerFunc defines an interface must be implemented by handlers.
type handlerFunc func(conn redisClient, params []string) (res interface{}, err error)

// impl is the pointer to the plugin implementation.
var impl Plugin

// whereToConnect builds a URI based on key's parameters and a configuration file.
func whereToConnect(params []string, sessions map[string]*Session, defaultURI string) (u *URI, err error) {
	const user = "zabbix"

	password := ""
	if len(params) > 1 {
		password = params[1]
	}

	uri := defaultURI

	// The first param can be either a URI or a session identifier
	if len(params) > 0 && len(params[0]) > 0 {
		if isLooksLikeURI(params[0]) {
			// Use the URI defined as key's parameter
			uri = params[0]
		} else {
			if _, ok := sessions[params[0]]; !ok {
				return nil, errorUnknownSession
			}

			// Use a pre-defined session
			uri = sessions[params[0]].URI
			password = sessions[params[0]].Password
		}
	}

	if len(password) > 0 {
		return newURIWithCreds(uri, user, password)
	}

	return parseURI(uri)
}

// Export implements the Exporter interface.
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	var (
		handleMetric  handlerFunc
		handlerParams []string
		zbxErr        zabbixError
	)

	uri, err := whereToConnect(params, p.options.Sessions, p.options.URI)
	if err != nil {
		return nil, err
	}

	// Extract handler related params
	if len(params) > commonParamsNum {
		handlerParams = params[commonParamsNum:]
	}

	switch key {
	case keyInfo:
		handleMetric = infoHandler // redis.info[[connString][,password][,section]]

	case keyPing:
		handleMetric = pingHandler // redis.ping[[connString][,password]]

	case keyConfig:
		handleMetric = configHandler // redis.config[[connString][,password][,pattern]]

	case keySlowlog:
		handleMetric = slowlogHandler // redis.slowlog[[connString][,password]]

	default:
		return nil, errorUnsupportedMetric
	}

	conn, err := p.connMgr.GetConnection(*uri)
	if err != nil {
		// Special logic of processing connection errors is used if redis.ping is requested
		// because it must return pingFailed if any error occurred.
		if key == keyPing {
			return pingFailed, nil
		}

		p.Errf(err.Error())

		return nil, errors.New(formatZabbixError(err.Error()))
	}

	result, err = handleMetric(conn, handlerParams)
	if err != nil {
		p.Errf(err.Error())

		if errors.As(err, &zbxErr) {
			return nil, zbxErr
		}
	}

	return result, nil
}

// Start implements the Runner interface and performs initialization when plugin is activated.
func (p *Plugin) Start() {
	p.connMgr = NewConnManager(
		time.Duration(p.options.KeepAlive)*time.Second,
		time.Duration(p.options.Timeout)*time.Second,
		hkInterval*time.Second,
	)
}

// Stop implements the Runner interface and frees resources when plugin is deactivated.
func (p *Plugin) Stop() {
	p.connMgr.Destroy()
	p.connMgr = nil
}

func init() {
	plugin.RegisterMetrics(&impl, pluginName,
		keyInfo, "Returns output of INFO command.",
		keyPing, "Test if connection is alive or not.",
		keyConfig, "Returns configuration parameters of Redis server.",
		keySlowlog, "Returns the number of slow log entries since Redis has been started.")
}
