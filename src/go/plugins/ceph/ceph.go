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

	"golang.zabbix.com/agent2/plugins/ceph/conn"
	"golang.zabbix.com/agent2/plugins/ceph/handlers"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/metric"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/uri"
)

const pluginName = "Ceph"

var _ plugin.Runner = (*Plugin)(nil)
var _ plugin.Exporter = (*Plugin)(nil)

// impl is the pointer to the plugin implementation.
var impl Plugin //nolint:gochecknoglobals // this is legacy implementation

// Plugin inherits plugin.Base and store plugin-specific data.
type Plugin struct {
	plugin.Base

	connMgr *conn.Manager
	options pluginOptions
	client  *http.Client
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

	u, err := uri.NewWithCreds(params["URI"], params["User"], params["APIKey"], uriDefaults)
	if err != nil {
		return nil, errs.Wrap(err, "failed to create URI")
	}

	handlerKey := handlers.Key(key)

	meta := handlers.GetMetricMeta(handlerKey)
	responses := make(map[handlers.Command][]byte)

	var resCh <-chan *response
	switch params["Mode"] {
	case "restful":

		ctx, cancel := context.WithCancel(context.Background())
		defer cancel()

		resCh = asyncRequest(ctx, cancel, p.client, u.String(), meta)
	case "native":
		ch := make(chan *response, len(meta.Commands))

		go func() {
			conn, err := p.connMgr.GetConnection(u, params)
			if err != nil {
				ch <- &response{
					command: "status",
					err:     err,
				}

				return
			}

			cmd := []byte(`{"prefix":"status", "format":"json", "detail":"detail"}`)

			res, _, err := conn.Client.MonCommand(cmd)
			if err != nil {
				ch <- &response{
					command: "status",
					err:     err,
				}
			}

			ch <- &response{
				command: "status",
				data:    res,
				err:     nil,
			}
		}()

		resCh = ch
	}

	for range meta.Commands {
		r := <-resCh
		if r.err != nil {
			err = r.err

			break
		}

		responses[r.command] = r.data
	}

	if err != nil {
		// Special logic of processing connection errors is used if keyPing is requested
		// because it must return pingFailed if any error occurred.
		if handlerKey == handlers.KeyPing {
			return handlers.PingFailed, nil
		}

		return nil, err
	}

	result, err := meta.Handle(responses)
	if err != nil {
		p.Errf(err.Error())

		return nil, errs.Wrap(err, "failed to execute command")
	}

	return result, nil
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
