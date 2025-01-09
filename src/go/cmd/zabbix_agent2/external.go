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

package main

import (
	"os"
	"path/filepath"
	"time"

	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/plugins/external"
	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
)

type pluginOptions struct {
	System struct {
		Path string
	}
}

func initExternalPlugins(options *agent.AgentOptions) (string, error) {
	paths := make(map[string]string)

	for name, p := range options.Plugins {
		var o pluginOptions
		if err := conf.Unmarshal(p, &o, false); err != nil {
			// not an external plugin, just ignore the error
			continue
		}

		if !filepath.IsAbs(o.System.Path) {
			return "", errs.Errorf("path %q not absolute", o.System.Path)
		}

		paths[name] = o.System.Path
	}

	if len(paths) == 0 {
		return "", nil
	}

	timeout := getTimeout()
	socket := agent.Options.ExternalPluginsSocket

	err := os.RemoveAll(socket)
	if err != nil {
		return "", errs.Wrapf(err, "failed to remove plugin socket, with path %q", socket)
	}

	listener, err := getListener(socket)
	if err != nil {
		return "", errs.Wrap(err, "failed to get socket listener")
	}

	for name, path := range paths {
		log.Debugf("initializing external plugin %q", name)

		// configuratorTask from internal/agent/scheduler/task.go depends
		// on loadable plugin configs not containing Path field, hence
		// it needs to removed.
		config := removePathField(options.Plugins[name])
		options.Plugins[name] = config

		accessor := external.NewPlugin(
			name,
			path,
			socket,
			timeout,
			listener,
		)

		err := accessor.RegisterMetrics(config)
		if err != nil {
			return "", errs.Wrapf(err, "failed to register metrics of plugin %q", name)
		}
	}

	return socket, nil
}

func getTimeout() time.Duration {
	if agent.Options.ExternalPluginTimeout == 0 {
		return time.Second * time.Duration(agent.Options.Timeout)
	}

	return time.Second * time.Duration(agent.Options.ExternalPluginTimeout)
}

func removePathField(privateOptions any) any {
	if root, ok := privateOptions.(*conf.Node); ok {
		for i, v := range root.Nodes {
			if node, ok := v.(*conf.Node); ok {
				if node.Name == "Path" {
					root.Nodes = remove(root.Nodes, i)
					return root
				}
			}
		}
	}

	return privateOptions
}

func remove(s []any, i int) []any {
	s[i] = s[len(s)-1]
	return s[:len(s)-1]
}
