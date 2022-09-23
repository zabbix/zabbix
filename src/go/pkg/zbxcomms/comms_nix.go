//go:build !windows
// +build !windows

/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

package zbxcomms

import (
	"fmt"
	"net"

	"zabbix.com/pkg/tls"
)

func Listen(address string, args ...interface{}) (c *Listener, err error) {
	var tlsconfig *tls.Config

	if len(args) > 0 {
		var ok bool
		if tlsconfig, ok = args[0].(*tls.Config); !ok {
			return nil, fmt.Errorf("invalid TLS configuration parameter of type %T", args[0])
		}
	}
	l, tmperr := net.Listen("tcp", address)
	if tmperr != nil {
		return nil, fmt.Errorf("Listen failed: %s", tmperr.Error())
	}
	c = &Listener{listener: l.(*net.TCPListener), tlsconfig: tlsconfig}

	return
}
