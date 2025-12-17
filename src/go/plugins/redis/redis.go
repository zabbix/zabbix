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

package redis

import (
	"time"

	"golang.zabbix.com/agent2/plugins/redis/conn"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/metric"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/plugin/comms"
	"golang.zabbix.com/sdk/uri"
	"golang.zabbix.com/sdk/zbxerr"
)

// impl is the pointer to the plugin implementation.
//
//nolint:gochecknoglobals // legacy implementation
var impl Plugin

var _ plugin.Runner = (*Plugin)(nil)
var _ plugin.Exporter = (*Plugin)(nil)

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base

	connMgr *conn.Manager
	options pluginOptions
}

// Export implements the Exporter interface.
func (p *Plugin) Export(key string, rawParams []string, _ plugin.ContextProvider) (any, error) {
	params, _, hc, err := metrics[key].EvalParams(rawParams, p.options.Sessions)
	if err != nil {
		return nil, errs.Wrap(err, "failed to eval params")
	}

	err = metric.SetDefaults(params, hc, p.options.Default)
	if err != nil {
		return nil, errs.Wrap(err, "failed to set metric defaults")
	}

	redisURI, err := uri.NewWithCreds(params["URI"], params["User"], params["Password"], uriDefaults)
	if err != nil {
		return nil, errs.Wrap(err, "could not create URI for Redis")
	}

	handleMetric := getHandlerFunc(key)
	if handleMetric == nil {
		return nil, zbxerr.ErrorUnsupportedMetric
	}

	connection, err := p.connMgr.GetConnection(redisURI, params)
	if err != nil {
		// Special logic of processing connection errors is used if redis.ping is requested
		// because it must return pingFailed if any error occurred.
		if key == keyPing {
			return comms.PingFailed, nil
		}

		p.Errf(err.Error())

		return nil, errs.Wrap(err, "failed to get connection to Redis")
	}

	result, err := handleMetric(connection, params)
	if err != nil {
		p.Errf(err.Error())
	}

	return result, nil
}

// Start implements the Runner interface and performs initialization when plugin is activated.
func (p *Plugin) Start() {
	p.connMgr = conn.NewManager(
		p.Logger,
		time.Duration(p.options.KeepAlive)*time.Second,
		time.Duration(p.options.Timeout)*time.Second,
		conn.HouseKeeperInterval*time.Second,
	)
}

// Stop implements the Runner interface and frees resources when plugin is deactivated.
func (p *Plugin) Stop() {
	p.connMgr.Destroy()
	p.connMgr = nil
}
