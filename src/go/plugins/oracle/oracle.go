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

package oracle

import (
	"context"
	"net/http"
	"time"
	"zabbix.com/pkg/zbxerr"

	"github.com/omeid/go-yarn"

	"zabbix.com/pkg/plugin"
)

const pluginName = "Oracle"

const hkInterval = 10

// Common params: [connString][,user][,password][,service]
const commonParamsNum = 4

const sqlExt = ".sql"

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base
	connMgr *ConnManager
	options PluginOptions
}

// impl is the pointer to the plugin implementation.
var impl Plugin

// whereToConnect builds a URI based on key's parameters and a configuration file.
func whereToConnect(params []string, options *PluginOptions) (u *URI, err error) {
	user := ""
	if len(params) > 1 {
		user = params[1]
	}

	password := ""
	if len(params) > 2 {
		password = params[2]
	}

	serviceName := options.ServiceName
	if len(params) > 3 {
		serviceName = params[3]
	}

	uri := options.URI

	// The first param can be either a URI or a session identifier
	if len(params) > 0 && len(params[0]) > 0 {
		if isLooksLikeURI(params[0]) {
			// Use a URI defined as key's parameter
			uri = params[0]
		} else {
			if _, ok := options.Sessions[params[0]]; !ok {
				return nil, zbxerr.ErrorUnknownSession
			}
			// Use a pre-defined session
			uri = options.Sessions[params[0]].URI
			user = options.Sessions[params[0]].User
			password = options.Sessions[params[0]].Password
			serviceName = options.Sessions[params[0]].ServiceName
		}
	}

	return newURIWithCreds(uri, user, password, serviceName)
}

// Export implements the Exporter interface.
func (p *Plugin) Export(key string, params []string, _ plugin.ContextProvider) (result interface{}, err error) {
	var (
		handlerParams []string
	)

	uri, err := whereToConnect(params, &p.options)
	if err != nil {
		return nil, err
	}

	// Extract handler related params
	if len(params) > commonParamsNum {
		handlerParams = params[commonParamsNum:]
	}

	handleMetric := getHandlerFunc(key)
	if handleMetric == nil {
		return nil, zbxerr.ErrorUnsupportedMetric
	}

	conn, err := p.connMgr.GetConnection(*uri)
	if err != nil {
		// Special logic of processing connection errors should be used if oracle.ping is requested
		// because it must return pingFailed if any error occurred.
		if key == keyPing {
			return pingFailed, nil
		}

		p.Errf(err.Error())

		return nil, err
	}

	ctx, cancel := context.WithTimeout(conn.ctx, conn.callTimeout)
	defer cancel()

	result, err = handleMetric(ctx, conn, handlerParams)

	if err != nil {
		p.Errf(err.Error())
	}

	return
}

// Start implements the Runner interface and performs initialization when plugin is activated.
func (p *Plugin) Start() {
	queryStorage, err := yarn.New(http.Dir(p.options.CustomQueriesPath), "*"+sqlExt)
	if err != nil {
		p.Errf(err.Error())
		// create empty storage if error occurred
		queryStorage = yarn.NewFromMap(map[string]string{})
	}

	p.connMgr = NewConnManager(
		time.Duration(p.options.KeepAlive)*time.Second,
		time.Duration(p.options.ConnectTimeout)*time.Second,
		time.Duration(p.options.CallTimeout)*time.Second,
		hkInterval*time.Second,
		queryStorage,
	)
}

// Stop implements the Runner interface and frees resources when plugin is deactivated.
func (p *Plugin) Stop() {
	p.connMgr.Destroy()
	p.connMgr = nil
}
