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

package memcached

import (
	"fmt"
	"time"

	"golang.zabbix.com/sdk/metric"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/uri"
	"golang.zabbix.com/sdk/zbxerr"
)

const pluginName = "Memcached"

const hkInterval = 10

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base
	connMgr *ConnManager
	options PluginOptions
}

// impl is the pointer to the plugin implementation.
var impl Plugin

// Export implements the Exporter interface.
func (p *Plugin) Export(key string, rawParams []string, _ plugin.ContextProvider) (result interface{}, err error) {
	params, _, hc, err := metrics[key].EvalParams(rawParams, p.options.Sessions)
	if err != nil {
		return nil, err
	}

	err = metric.SetDefaults(params, hc, p.options.Default)
	if err != nil {
		return nil, err
	}

	if len(params["User"]+params["Password"]) > maxEntryLen {
		return nil, zbxerr.ErrorInvalidParams.Wrap(
			fmt.Errorf("credentials cannot be longer "+"than %d characters", maxEntryLen))
	}

	uri, err := uri.NewWithCreds(params["URI"], params["User"], params["Password"], uriDefaults)
	if err != nil {
		return nil, err
	}

	handleMetric := getHandlerFunc(key)
	if handleMetric == nil {
		return nil, zbxerr.ErrorUnsupportedMetric
	}

	result, err = handleMetric(p.connMgr.GetConnection(*uri), params)
	if err != nil {
		p.Errf(err.Error())
	}

	return result, err
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
