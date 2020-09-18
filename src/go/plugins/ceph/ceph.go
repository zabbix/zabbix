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
	"crypto/tls"
	"net/http"
	"time"

	"zabbix.com/pkg/zbxerr"

	"zabbix.com/pkg/plugin"
)

const pluginName = "Ceph"

// Common params: [connString][,user][,apikey]
const commonParamsNum = 3

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base
	options PluginOptions
	client  *http.Client
}

// impl is the pointer to the plugin implementation.
var impl Plugin

// whereToConnect builds a URI based on key's parameters and a configuration file.
func whereToConnect(params []string, sessions map[string]*Session, defaultURI string) (u *URI, err error) {
	user := ""
	if len(params) > 1 {
		user = params[1]
	}

	apikey := ""
	if len(params) > 2 {
		apikey = params[2]
	}

	uri := defaultURI

	// The first param can be either a URI or a session identifier
	if len(params) > 0 && len(params[0]) > 0 {
		if isLooksLikeURI(params[0]) {
			// Use a URI defined as key's parameter
			uri = params[0]
		} else {
			if _, ok := sessions[params[0]]; !ok {
				return nil, zbxerr.ErrorUnknownSession
			}

			// Use a pre-defined session
			uri = sessions[params[0]].URI
			user = sessions[params[0]].User
			apikey = sessions[params[0]].ApiKey
		}
	}

	return newURIWithCreds(uri, user, apikey)
}

// Export implements the Exporter interface.
func (p *Plugin) Export(key string, params []string, _ plugin.ContextProvider) (result interface{}, err error) {
	uri, err := whereToConnect(params, p.options.Sessions, p.options.URI)
	if err != nil {
		return nil, zbxerr.New(err.Error())
	}

	if len(params) > commonParamsNum {
		return nil, zbxerr.ErrorTooManyParameters
	}

	metric := metrics[key]

	responses := make([][]byte, len(metric.cmd))

	for i, cmd := range metric.cmd {
		responses[i], err = request(p.client, uri.String(), cmd, metric.params)
		if err != nil {
			// Special logic of processing connection errors is used if keyPing is requested
			// because it must return pingFailed if any error occurred.
			if key == keyPing {
				return pingFailed, nil
			}

			return nil, err
		}
	}

	result, err = metric.Handle(responses...)
	if err != nil {
		p.Errf(err.Error())
	}

	return
}

// Start implements the Runner interface and performs initialization when plugin is activated.
func (p *Plugin) Start() {
	p.client = &http.Client{
		Timeout: time.Duration(p.options.Timeout) * time.Second,
	}

	p.client.Transport = &http.Transport{
		DisableKeepAlives: false,
		IdleConnTimeout:   time.Duration(p.options.KeepAlive) * time.Second,
		TLSClientConfig:   &tls.Config{InsecureSkipVerify: p.options.InsecureSkipVerify},
	}
}

// Stop implements the Runner interface and frees resources when plugin is deactivated.
func (p *Plugin) Stop() {
	p.client.CloseIdleConnections()
	p.client = nil
}
