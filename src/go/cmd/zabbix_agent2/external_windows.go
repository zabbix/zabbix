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
	"fmt"
	"net"

	"github.com/Microsoft/go-winio"
	"golang.zabbix.com/agent2/plugins/external"
)

func getListener(socket string) (listener net.Listener, err error) {
	listener, err = winio.ListenPipe(socket, nil)
	if err != nil {
		err = fmt.Errorf(
			"failed to create plugin listener with socket path, %s, %s", socket, err.Error(),
		)

		return
	}

	return
}

func cleanUpExternal() {}

func checkExternalExits() error {
	return nil
}

func checkExternalExit() error {
	return nil
}

func listenOnPluginFail(p *external.Plugin, name string) {}
