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
	"bufio"
	"errors"
	"net"
	"os"
	"time"

	"git.zabbix.com/ap/plugin-support/log"
)

type Conn struct {
	listener net.Listener
	sink     chan *Client
	last_err string
	stopped  bool
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
	c.stopped = true
	if c.listener != nil {
		c.listener.Close()
	}
}

func (c *Conn) handleError(err error) error {
	var netErr net.Error

	if !errors.As(err, &netErr) {
		log.Errf("failed to accept an incoming connection: %s", err.Error())

		return nil
	}

	if netErr.Timeout() {
		log.Debugf("failed to accept an incoming connection: %s", err.Error())

		return nil
	}

	if c.stopped {
		return err
	}

	log.Errf("failed to accept an incoming connection: %s", err.Error())

	var se *os.SyscallError

	if !errors.As(err, &se) {
		return nil
	}

	/* sleep to avoid high CPU usage on surprising temporary errors */
	if c.last_err == se.Err.Error() {
		time.Sleep(time.Second)
	}
	c.last_err = se.Err.Error()

	return nil
}

func (c *Conn) run() {
	for {
		conn, err := c.listener.Accept()
		if err != nil {
			if c.handleError(err) == nil {
				continue
			}

			break
		}

		scanner := bufio.NewScanner(conn)
		if scanner.Scan() {
			// accept single command line, the connection will be closed after sending reply
			c.sink <- &Client{request: scanner.Text(), conn: conn}
		} else {
			conn.Close()
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
