/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package comms

import (
	"bytes"
	"encoding/binary"
	"fmt"
	"io"
	"net"
	"time"
)

const headerSize = 13

type ZbxConnection struct {
	conn net.Conn
}

type ZbxListener struct {
	listener net.Listener
}

func (c *ZbxConnection) Open(address string, timeout time.Duration) (err error) {
	c.conn, err = net.DialTimeout("tcp", address, timeout)
	return
}

func write(w io.Writer, data []byte) error {
	var b bytes.Buffer

	b.Grow(len(data) + headerSize)
	b.Write([]byte{'Z', 'B', 'X', 'D', 0x01})
	err := binary.Write(&b, binary.LittleEndian, uint64(len(data)))
	if nil != err {
		return err
	}
	b.Write(data)
	_, err = w.Write(b.Bytes())

	return err
}

func (c *ZbxConnection) Write(data []byte, timeout time.Duration) error {
	err := c.conn.SetWriteDeadline(time.Now().Add(timeout))
	if nil != err {
		return err
	}
	return write(c.conn, data)
}

func (c *ZbxConnection) WriteString(s string, timeout time.Duration) error {
	return c.Write([]byte(s), timeout)
}

func read(r io.Reader) ([]byte, error) {
	const maxRecvDataSize = 128 * 1048576
	var total int
	var b [2048]byte

	s := b[:]

	for total < headerSize {
		n, err := r.Read(s[total:])
		if err != nil && err != io.EOF {
			return nil, fmt.Errorf("Cannot read message: '%s'", err)
		}

		if n == 0 {
			break
		}

		total += n
	}

	if total < 13 {
		if total == 0 {
			return []byte{}, nil
		}
		return nil, fmt.Errorf("Message is missing header.")
	}

	if !bytes.Equal(s[:4], []byte{'Z', 'B', 'X', 'D'}) {
		return nil, fmt.Errorf("Message is using unsupported protocol.")
	}

	if s[4] != 0x01 {
		return nil, fmt.Errorf("Message is using unsupported protocol version.")
	}

	expectedSize := binary.LittleEndian.Uint32(s[5:9])

	if expectedSize > maxRecvDataSize {
		return nil, fmt.Errorf("Message size %d exceeds the maximum size %d bytes.", expectedSize, maxRecvDataSize)
	}

	if int(expectedSize) < total-headerSize {
		return nil, fmt.Errorf("Message is longer than expected.")
	}

	if int(expectedSize) == total-headerSize {
		return s[headerSize:total], nil
	}

	sTmp := make([]byte, expectedSize+1)
	if total > headerSize {
		copy(sTmp, s[headerSize:total])
	}
	s = sTmp
	total = total - headerSize

	for total < int(expectedSize) {
		n, err := r.Read(s[total:])
		if err != nil {
			return nil, err
		}

		if n == 0 {
			break
		}

		total += n
	}

	if total != int(expectedSize) {
		return nil, fmt.Errorf("Message size is shorted or longer than expected.")
	}

	return s[:total], nil
}

func (c *ZbxConnection) Read(timeout time.Duration) ([]byte, error) {
	err := c.conn.SetReadDeadline(time.Now().Add(timeout))
	if nil != err {
		return nil, err
	}
	return read(c.conn)
}

func Listen(address string) (c *ZbxListener, err error) {
	l, tmperr := net.Listen("tcp", address)
	if tmperr != nil {
		return nil, fmt.Errorf("Listen failed: %s", tmperr.Error())
	}
	c = &ZbxListener{listener: l.(*net.TCPListener)}
	return
}

func (l *ZbxListener) Accept() (c *ZbxConnection, err error) {
	var conn net.Conn
	if conn, err = l.listener.Accept(); err != nil {
		return
	} else {
		c = &ZbxConnection{conn: conn}
	}
	return
}

func (c *ZbxConnection) Close() (err error) {
	return c.conn.Close()
}

func (c *ZbxListener) Close() (err error) {
	return c.listener.Close()
}
