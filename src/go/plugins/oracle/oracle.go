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

package oracle

import (
	"strings"
	"time"

	"golang.zabbix.com/agent2/plugins/oracle/dbconn"
	"golang.zabbix.com/agent2/plugins/oracle/handlers"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/metric"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	pluginName = "Oracle"
)

// impl is the pointer to the plugin implementation.
var impl Plugin //nolint:gochecknoglobals

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base

	connMgr *dbconn.ConnManager
	options PluginOptions
}

// Export implements the Exporter interface.
//
//nolint:gocyclo,cyclop
func (p *Plugin) Export(key string, rawParams []string, _ plugin.ContextProvider) (any, error) {
	if key == keyCustomQuery && !p.options.CustomQueriesEnabled {
		return nil, errs.Errorf("key %q is disabled", keyCustomQuery)
	}

	if strings.TrimSpace(key) == "" {
		return nil, errs.Errorf("key cannot be empty")
	}

	params, extraParams, hardcodedParams, err := metrics[key].EvalParams(rawParams, p.options.Sessions)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorInvalidParams)
	}

	err = metric.SetDefaults(params, hardcodedParams, p.options.Default)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorInvalidParams)
	}

	handleMetric := metricsMeta[key]
	if handleMetric == nil {
		return nil, zbxerr.ErrorUnsupportedMetric
	}

	connDetails, err := dbconn.NewConnDetails(params["URI"], params["User"], params["Password"], params["Service"])
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorInvalidParams)
	}

	conn, err := p.connMgr.GetConnection(*connDetails)
	if err != nil {
		// Special logic of processing connection errors should be used if oracle.ping is requested,
		// because it must return pingFailed if any error occurred.
		if key == keyPing {
			return handlers.PingFailed, nil
		}

		p.Errf(err.Error())

		return nil, errs.Wrap(err, "get connection failed")
	}

	ctx, cancel := conn.GetContextWithTimeout()
	defer cancel()

	result, err := handleMetric(ctx, conn, params, extraParams...)

	if err != nil {
		p.Errf(err.Error())
	}

	return result, err
}

// Start implements the Runner interface and performs an initialization when the plugin is activated.
func (p *Plugin) Start() {
	opt := &dbconn.Options{
		KeepAlive:            time.Duration(p.options.KeepAlive) * time.Second,
		ConnectTimeout:       time.Duration(p.options.ConnectTimeout) * time.Second,
		CallTimeout:          time.Duration(p.options.CallTimeout) * time.Second,
		CustomQueriesEnabled: p.options.CustomQueriesEnabled,
		CustomQueriesPath:    p.options.CustomQueriesPath,
		ResolveTNS:           p.options.ResolveTNS,
	}
	p.connMgr = dbconn.NewConnManager(
		p.Logger,
		opt,
	)
}

// Stop implements the Runner interface and frees resources when plugin is deactivated.
func (p *Plugin) Stop() {
	p.connMgr.Destroy()
	p.connMgr = nil
}
