/*
** Copyright (C) 2001-2026 Zabbix SIA
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

package docker

import (
	"context"
	"net"
	"net/http"
	"strings"
	"time"

	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

// Options is a plugin configuration.
type Options struct {
	Endpoint string `conf:"default=unix:///var/run/docker.sock"`
	Timeout  int    `conf:"optional,range=1:30"`
}

// Configure implements the plugin.Configurator interface.
// Initializes configuration structures.
func (p *Plugin) Configure(global *plugin.GlobalOptions, options any) {
	err := conf.UnmarshalStrict(options, &p.options)
	if err != nil {
		p.Errf("cannot unmarshal configuration options: %s", err)
	}

	if p.options.Timeout == 0 {
		p.options.Timeout = global.Timeout
	}

	socketPath := strings.Split(p.options.Endpoint, "://")[1]
	transport := &http.Transport{
		DialContext: func(_ context.Context, _, _ string) (net.Conn, error) {
			return net.Dial("unix", socketPath)
		},
	}

	p.client = &http.Client{
		Transport: transport,
		Timeout:   time.Duration(p.options.Timeout) * time.Second,
	}
}

// Validate implements the plugin.Configurator interface.
// Returns an error if validation of a plugin's configuration is failed.
func (*Plugin) Validate(options any) error {
	var opts Options

	err := conf.UnmarshalStrict(options, &opts)
	if err != nil {
		return errs.Wrap(err, "cannot unmarshal plugin options")
	}

	endpointParts := strings.SplitN(opts.Endpoint, "://", 2)
	if len(endpointParts) == 1 || endpointParts[0] != "unix" {
		return errs.New("invalid endpoint format")
	}

	return nil
}
