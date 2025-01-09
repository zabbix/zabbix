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

package ceph

import (
	"context"
	"crypto/tls"
	"net/http"
	"time"

	"golang.zabbix.com/sdk/metric"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/uri"
)

const pluginName = "Ceph"

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base
	options PluginOptions
	client  *http.Client
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

	uri, err := uri.NewWithCreds(params["URI"], params["User"], params["APIKey"], uriDefaults)
	if err != nil {
		return nil, err
	}

	meta := metricsMeta[key]
	responses := make(map[command][]byte)

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	resCh := asyncRequest(ctx, cancel, p.client, uri.String(), meta)

	for range meta.commands {
		r := <-resCh
		if r.err != nil {
			if err == nil {
				err = r.err
			}

			break
		}

		responses[command(r.cmd)] = r.data
	}

	if err != nil {
		// Special logic of processing connection errors is used if keyPing is requested
		// because it must return pingFailed if any error occurred.
		if key == keyPing {
			return pingFailed, nil
		}

		return nil, err
	}

	result, err = meta.handle(responses)
	if err != nil {
		p.Errf(err.Error())
	}

	return result, err
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
