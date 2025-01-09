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

package zbxcomms

import (
	"context"
	"fmt"
	"net"
	"syscall"

	"golang.zabbix.com/agent2/pkg/tls"
)

func Listen(address string, args ...interface{}) (c *Listener, err error) {
	var tlsconfig *tls.Config

	if len(args) > 0 {
		var ok bool
		if tlsconfig, ok = args[0].(*tls.Config); !ok {
			return nil, fmt.Errorf("invalid TLS configuration parameter of type %T", args[0])
		}
	}

	// prevent other processes from binding to the same port
	// SO_EXCLUSIVEADDRUSE is mutually exclusive with SO_REUSEADDR
	// on Windows SO_REUSEADDR has different semantics than on Unix
	// https://msdn.microsoft.com/en-us/library/windows/desktop/ms740621(v=vs.85).aspx
	lc := net.ListenConfig{
		Control: func(network, address string, conn syscall.RawConn) error {
			var operr error
			if err := conn.Control(func(fd uintptr) {
				operr = syscall.SetsockoptInt(syscall.Handle(fd), syscall.SOL_SOCKET, ^syscall.SO_REUSEADDR, 1)
			}); err != nil {
				return err
			}
			return operr
		},
	}

	l, tmperr := lc.Listen(context.Background(), "tcp", address)
	if tmperr != nil {
		return nil, fmt.Errorf("Listen failed: %s", tmperr.Error())
	}
	c = &Listener{listener: l.(*net.TCPListener), tlsconfig: tlsconfig}

	return
}
