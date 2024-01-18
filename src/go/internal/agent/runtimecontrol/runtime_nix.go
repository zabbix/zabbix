//go:build !windows
// +build !windows

/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

package runtimecontrol

import (
	"fmt"
	"io"
	"net"
	"os"
	"syscall"
	"time"

	"git.zabbix.com/ap/plugin-support/log"
)

func New(path string, timeout time.Duration) (conn *Conn, err error) {
	c := Conn{}
	if path != "" {
		if _, tmperr := os.Stat(path); !os.IsNotExist(tmperr) {
			if _, err = SendCommand(path, "version", timeout); err == nil {
				return nil, fmt.Errorf(
					"An agent is already using control socket %s",
					path,
				)
			}
			if err = os.Remove(path); err != nil {
				return
			}
		}
		mask := syscall.Umask(0077)
		defer syscall.Umask(mask)
		if c.listener, err = net.Listen("unix", path); err != nil {
			return
		}
		c.sink = make(chan *Client)
		log.Debugf("listening for control connections on %s", path)
	}
	return &c, nil
}

func SendCommand(path, command string, timeout time.Duration) (string, error) {
	conn, err := net.DialTimeout("unix", path, timeout)
	if err != nil {
		return "", err
	}

	defer conn.Close()

	err = conn.SetDeadline(time.Now().Add(timeout))
	if err != nil {
		return "", err
	}

	_, err = conn.Write([]byte(command + "\n"))
	if err != nil {
		return "", err
	}

	b, err := io.ReadAll(conn)
	if err != nil {
		return "", err
	}

	return string(b), nil
}
