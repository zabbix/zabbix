//go:build !windows
// +build !windows

/*
** Copyright (C) 2001-2024 Zabbix SIA
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
	"errors"
	"fmt"
	"net"
	"syscall"

	"golang.zabbix.com/agent2/plugins/external"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin"
)

func getListener(socket string) (listener net.Listener, err error) {
	listener, err = net.Listen("unix", socket)
	if err != nil {
		err = fmt.Errorf(
			"failed to create plugin listener with socket path, %s, %s", socket, err.Error(),
		)

		return
	}

	return
}

func cleanUpExternal() {
	if pluginsocket != "" {
		err := syscall.Unlink(pluginsocket)
		if err != nil {
			log.Critf("failed to clean up after plugins, %s", err)
		}
	}
}

func checkExternalExits() error {
	var status syscall.WaitStatus
	pid, err := syscall.Wait4(-1, &status, syscall.WNOHANG, nil)
	if err != nil {
		log.Tracef("failed to obtain PID of dead child process: %s", err)
		return nil
	}

	for _, p := range plugin.Plugins {
		if p.IsExternal() {
			if ep, ok := p.(*external.Plugin); ok {
				return checkExternalExit(pid, ep, ep.Name())
			}
		}
	}

	return nil
}

func checkExternalExit(pid int, p *external.Plugin, name string) error {
	if p.CheckPid(pid) {
		p.Cleanup()
		return fmt.Errorf("plugin %s died", name)
	}

	return nil
}

func listenOnPluginFail(p *external.Plugin, name string) {
	var pid int
	var err error

	for {
		pid, err = syscall.Wait4(-1, nil, 0, nil)
		if err != nil {
			if errors.Is(err, syscall.EINTR) {
				continue
			}

			panic(fmt.Errorf("failed to obtain PID of dead child process: %w", err))
		}

		break
	}

	if err := checkExternalExit(pid, p, name); err != nil {
		panic(err)
	}
}
