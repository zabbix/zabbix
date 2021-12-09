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
	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/shared"
	"zabbix.com/plugins/external"
)

type pluginOptions struct {
	Path string
}

func initExternalPlugins(options *agent.AgentOptions) (string, error) {
	paths := make(map[string]string)

	for name, p := range options.Plugins {
		var o pluginOptions
		if err := conf.Unmarshal(p, &o, false); err != nil {
			return "", fmt.Errorf(`Invalid plugin '%s' configuration: %s`, name, err)
		}
		paths[name] = o.Path
	}

	if len(paths) == 0 {
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

	for name, p := range paths {
		accessor := createAccessor(p, socket, timeout, listener)
		err := initExternalPlugin(name, accessor, options)
		if err != nil {
			return "", err
		}

		plugin.RegisterMetrics(accessor, name, accessor.Params...)
	}

	return socket, nil
}

func initExternalPlugin(name string, p *external.Plugin, options *agent.AgentOptions) (err error) {
	p.Initial = true
	p.ExecutePlugin()
	defer p.Stop()

	var resp *shared.RegisterResponse
	resp, err = p.Register()
	if err != nil {
		return
	}

	if resp.Error != "" {
		return errors.New(resp.Error)
	}

	if name != resp.Name {
		return fmt.Errorf("missmatch plugin names %s and %s, with plugin path %s", name, resp.Name, p.Path)
	}

	p.SetBrokerName(name)
	p.Interfaces = resp.Interfaces
	p.Params = resp.Metrics
	p.Initial = false

	options.Plugins[name] = removePath(options.Plugins[name])

	err = validate(p, options.Plugins[name])
	if err != nil {
		return fmt.Errorf("[%s] %s", name, err.Error())
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

func removePath(privateOptions interface{}) interface{} {
	removeIndex := -1
	var node *conf.Node
	var ok bool

	if node, ok = privateOptions.(*conf.Node); ok {
		for i, node := range node.Nodes {
			if childNode, ok := node.(*conf.Node); ok {
				if childNode.Name == "Path" {
					removeIndex = i
				}
			}
		}
	}

	if removeIndex >= len(node.Nodes) || removeIndex == -1 {
		return node
	}

	node.Nodes = remove(node.Nodes, removeIndex)

	return node
}

func remove(s []interface{}, i int) []interface{} {
	s[i] = s[len(s)-1]
	return s[:len(s)-1]
}
