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
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
)

func initExternalPlugins(options *agent.AgentOptions, sysOptions agent.PluginSystemOptions) (string, error) {
	paths := make(map[string]string)

	for name, s := range sysOptions {
		// if path is not set it's an internal plugin
		if s.Path == nil {
			continue
		}

		if !filepath.IsAbs(*s.Path) {
			return "", errs.Errorf("loadable plugin %q path %q is not absolute", name, *s.Path)
		}

		paths[name] = *s.Path
	}

	if len(paths) == 0 {
		return "", nil
	}

	timeout := getTimeout()
	socket := options.ExternalPluginsSocket

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

		accessor := external.NewPlugin(
			name,
			path,
			socket,
			timeout,
			listener,
		)

		err := accessor.RegisterMetrics(options.Plugins[name])
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
