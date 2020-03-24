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
	"errors"
	"time"

	"zabbix.com/pkg/plugin"
)

const pluginName = "Memcached"

const hkInterval = 10

const (
	keyStats = "memcached.stats"
	keyPing  = "memcached.ping"
)

const commonParamsNum = 3

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base
	connMgr *ConnManager
	options PluginOptions
}

// handlerFunc defines an interface must be implemented by handlers.
type handlerFunc func(conn MCClient, params []string) (res interface{}, err error)

// impl is the pointer to the plugin implementation.
var impl Plugin

// whereToConnect builds a URI based on key's parameters and a configuration file.
func whereToConnect(params []string, sessions map[string]*Session, defaultURI string) (u *URI, err error) {
	user := ""
	if len(params) > 1 {
		user = params[1]
	}

	password := ""
	if len(params) > 2 {
		password = params[2]
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
			user = sessions[params[0]].User
			password = sessions[params[0]].Password
		}
	}

	if len(user) > 0 || len(password) > 0 {
		return newURIWithCreds(uri, user, password)
	}

	return parseURI(uri)
}

// Export implements the Exporter interface.
func (p *Plugin) Export(key string, params []string, _ plugin.ContextProvider) (result interface{}, err error) {
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
	case keyStats:
		handleMetric = statsHandler // memcached.stats[[connString][,user][,password][,type]]

	case keyPing:
		handleMetric = pingHandler // memcached.ping[[connString][,user][,password]]

	default:
		return nil, errorUnsupportedMetric
	}

	result, err = handleMetric(p.connMgr.GetConnection(*uri), handlerParams)
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
		keyStats, "Returns output of stats command.",
		keyPing, "Test if connection is alive or not.")
}
