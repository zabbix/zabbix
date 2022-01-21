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

package remotecontrol

import (
	"bufio"
	"net"
)

type Conn struct {
	listener net.Listener
	sink     chan *Client
}

type Client struct {
	request string
	conn    net.Conn
}

type Exchanger interface {
	Request() string
	Reply(response string) error
	Close()
}

func (c *Client) Request() (cmmand string) {
	return c.request
}

func (c *Client) Reply(response string) (err error) {
	_, err = c.conn.Write([]byte(response))
	return
}

func (c *Client) Close() {
	c.conn.Close()
}

func (c *Conn) Stop() {
	if c.listener != nil {
		c.listener.Close()
	}
}

func (c *Conn) run() {
	for {
		if conn, err := c.listener.Accept(); err != nil && !err.(net.Error).Temporary() {
			break
		} else {
			scanner := bufio.NewScanner(conn)
			if scanner.Scan() {
				// accept single command line, the connection will be closed after sending reply
				c.sink <- &Client{request: scanner.Text(), conn: conn}
			} else {
				conn.Close()
			}
		}
	}
}

func (c *Conn) Start() {
	if c.listener != nil {
		go c.run()
	}
}

func (c *Conn) Client() (client chan *Client) {
	return c.sink
}
