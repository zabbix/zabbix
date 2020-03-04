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

package memcached

import (
	"time"

	"zabbix.com/pkg/plugin"
)

const pluginName = "Memcached"

const hkInterval = 10

const (
	keyStats = "memcached.stats"
	keyPing  = "memcached.ping"
)

// maxParams defines the maximum number of parameters for metrics.
var maxParams = map[string]int{
	keyStats: 2,
	keyPing:  1,
}

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base
	connMgr *ConnManager
	options PluginOptions
}

type handler func(conn MCClient, params []string) (res interface{}, err error)

// impl is the pointer to the plugin implementation.
var impl Plugin

// Export implements the Exporter interface.
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	var (
		uri     URI
		handler handler
	)

	// The first param can be either a URI or a session identifier
	if len(params) > 0 && len(params[0]) > 0 {
		if isLooksLikeURI(params[0]) {
			// Use the URI defined as key's parameter
			uri, err = newURIWithCreds(params[0], p.options.User, p.options.Password)
		} else {
			if _, ok := p.options.Sessions[params[0]]; !ok {
				return nil, errorUnknownSession
			}
			// Use a pre-defined session
			uri, err = newURIWithCreds(
				p.options.Sessions[params[0]].URI,
				p.options.Sessions[params[0]].User,
				p.options.Sessions[params[0]].Password,
			)
		}
	} else {
		// Use the default URI if the first param is omitted
		uri, err = newURIWithCreds(p.options.URI, p.options.User, p.options.Password)
	}

	if err != nil {
		return nil, err
	}

	switch key {
	case keyStats:
		handler = p.statsHandler // memcached.stats[[uri][,type]]

	case keyPing:
		handler = p.pingHandler // memcached.ping[[uri]]

	default:
		return nil, errorUnsupportedMetric
	}

	if len(params) > maxParams[key] {
		return nil, errorInvalidParams
	}

	return handler(p.connMgr.GetConnection(uri), params)
}

func (p *Plugin) Start() {
	p.connMgr = NewConnManager(
		time.Duration(p.options.KeepAlive)*time.Second,
		time.Duration(p.options.Timeout)*time.Second,
		time.Duration(hkInterval)*time.Second,
	)
}

func (p *Plugin) Stop() {
	p.connMgr.Destroy()
	p.connMgr = nil
}

// init registers metrics.
func init() {
	plugin.RegisterMetrics(&impl, pluginName,
		keyStats, "Returns output of stats command.",
		keyPing, "Test if connection is alive or not.")
}
