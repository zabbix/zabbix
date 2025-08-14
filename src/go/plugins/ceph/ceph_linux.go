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

*
 */
package ceph

import (
	"crypto/tls"
	"net/http"
	"time"

	"golang.zabbix.com/agent2/plugins/ceph/conn"
	"golang.zabbix.com/agent2/plugins/ceph/handlers"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/uri"
)

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base

	connMgr *conn.Manager
	options pluginOptions
	client  *http.Client
}

// Start implements the Runner interface and performs initialization when plugin is activated.
func (p *Plugin) Start() {
	p.client = &http.Client{
		Timeout: time.Duration(p.options.Timeout) * time.Second,
	}

	p.client.Transport = &http.Transport{
		DisableKeepAlives: false,
		IdleConnTimeout:   time.Duration(p.options.KeepAlive) * time.Second,
		TLSClientConfig:   &tls.Config{InsecureSkipVerify: p.options.InsecureSkipVerify}, //nolint:gosec // user defined
	}

	p.connMgr = conn.NewManager(
		time.Duration(p.options.KeepAlive)*time.Second,
		p.options.Timeout, // time in seconds
		p.Logger,
	)
}

// Stop implements the Runner interface and frees resources when plugin is deactivated.
func (p *Plugin) Stop() {
	p.client.CloseIdleConnections()
	p.client = nil

	p.connMgr.Close()
	p.connMgr = nil
}

func (p *Plugin) handleNativeMode(u *uri.URI, meta *handlers.MetricMeta) (<-chan *response, error) {
	connection, err := p.connMgr.GetConnection(u)
	if err != nil {
		return nil, errs.Wrap(err, "failed to get connection")
	}

	return p.asyncNativeRequest(connection, meta), nil
}
