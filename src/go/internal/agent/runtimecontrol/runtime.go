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

package runtimecontrol

import (
	"bufio"
	"errors"
	"net"
	"os"
	"time"

	"golang.zabbix.com/sdk/log"
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

func (c *Client) Request() (command string) {
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
