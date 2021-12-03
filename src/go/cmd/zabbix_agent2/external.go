/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

package main

import (
	"errors"
	"fmt"
	"net"
	"os"
	"time"

	"zabbix.com/internal/agent"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/shared"
	"zabbix.com/plugins/external"
)

func initExternalPlugins(options *agent.AgentOptions) (string, error) {
	if len(options.ExternalPlugins) == 0 {
		return "", nil
	}

	timeout := parseTimeout()
	socket := agent.Options.ExternalPluginsSocket
	err := removeSocket(socket)
	if err != nil {
		return "", err
	}

	listener, err := getListener(socket)
	if err != nil {
		return "", err
	}

	for _, p := range options.ExternalPlugins {
		accessor := createAccessor(p, socket, timeout, listener)
		name, err := initExternalPlugin(accessor, options)
		if err != nil {
			return "", err
		}

		plugin.RegisterMetrics(accessor, name, accessor.Params...)
	}

	return socket, nil
}

func initExternalPlugin(p *external.Plugin, options *agent.AgentOptions) (name string, err error) {
	p.Initial = true
	p.ExecutePlugin()
	defer p.Stop()

	var resp *shared.RegisterResponse
	resp, err = p.Register()
	if err != nil {
		return
	}

	if resp.Error != "" {
		return "", errors.New(resp.Error)
	}

	name = resp.Name

	p.SetBrokerName(name)
	p.Interfaces = resp.Interfaces
	p.Params = resp.Metrics
	p.Initial = false

	err = validate(p, options.Plugins[name])
	if err != nil {
		return
	}

	return
}

func validate(p *external.Plugin, options interface{}) error {
	if !shared.ImplementsConfigurator(p.Interfaces) {
		return nil
	}

	return p.Validate(options)
}

func createAccessor(path, socket string, timeout time.Duration, listener net.Listener) *external.Plugin {
	accessor := &external.Plugin{
		Path:     path,
		Socket:   socket,
		Initial:  true,
		Listener: listener,
		Timeout:  timeout,
	}

	accessor.SetExternal(true)

	return accessor
}

func parseTimeout() (timeout time.Duration) {
	if agent.Options.ExternalPluginTimeout == 0 {
		timeout = time.Second * time.Duration(agent.Options.Timeout)

		return
	}

	timeout = time.Second * time.Duration(agent.Options.ExternalPluginTimeout)

	return
}

func removeSocket(socket string) error {
	if err := os.RemoveAll(socket); err != nil {
		return fmt.Errorf("failed to drop external plugin socket, with path %s, %s", socket, err.Error())
	}

	return nil
}
