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
	"bytes"
	"compress/zlib"
	"encoding/binary"
	"errors"
	"fmt"
	"io"
	"net"
	"time"

	"git.zabbix.com/ap/plugin-support/log"
	"zabbix.com/pkg/tls"
)

const (
	TimeoutModeFixed = iota
	TimeoutModeShift
)

const headerSize = 4 + 1 + 4 + 4
const tcpProtocol = byte(0x01)
const zlibCompress = byte(0x02)

const (
	connStateAccept = iota + 1
	connStateConnect
	connStateEstablished
)

type Connection struct {
	conn        net.Conn
	tlsConfig   *tls.Config
	state       int
	compress    bool
	timeout     time.Duration
	timeoutMode int
}

type Listener struct {
	listener  net.Listener
	tlsconfig *tls.Config
}

func open(address string, localAddr *net.Addr, timeout time.Duration, connect_timeout time.Duration, timeoutMode int,
	args ...interface{}) (c *Connection, err error) {
	c = &Connection{state: connStateConnect, compress: true, timeout: timeout, timeoutMode: timeoutMode}
	d := net.Dialer{Timeout: connect_timeout, LocalAddr: *localAddr}
	c.conn, err = d.Dial("tcp", address)

	if nil != err {
		return
	}
	if err = c.conn.SetDeadline(time.Now().Add(timeout)); err != nil {
		return
	}
	var tlsconfig *tls.Config
	if len(args) > 0 {
		var ok bool
		if tlsconfig, ok = args[0].(*tls.Config); !ok {
			return nil, fmt.Errorf("invalid TLS configuration parameter of type %T", args[0])
		}
		if tlsconfig != nil {
			c.conn, err = tls.NewClient(c.conn, tlsconfig, timeout, timeoutMode == TimeoutModeShift, address)
		}
	}
	return
}

func (c *Connection) write(w io.Writer, data []byte) (err error) {
	var buf bytes.Buffer
	flags := tcpProtocol
	if c.compress {
		z := zlib.NewWriter(&buf)
		if _, err = z.Write(data); err != nil {
			return
		}
		z.Close()
		flags |= zlibCompress
	} else {
		buf.Write(data)
	}

	var b bytes.Buffer
	b.Grow(buf.Len() + headerSize)
	b.Write([]byte{'Z', 'B', 'X', 'D', flags})
	if err = binary.Write(&b, binary.LittleEndian, uint32(buf.Len())); nil != err {
		return err
	}
	if err = binary.Write(&b, binary.LittleEndian, uint32(len(data))); nil != err {
		return err
	}
	b.Write(buf.Bytes())
	_, err = w.Write(b.Bytes())

	return err
}

func (c *Connection) Write(data []byte) error {
	if c.timeoutMode == TimeoutModeShift {
		if err := c.conn.SetWriteDeadline(time.Now().Add(c.timeout)); err != nil {
			return err
		}
	}

	return c.write(c.conn, data)
}

func (c *Connection) WriteString(s string) error {
	return c.Write([]byte(s))
}

func (c *Connection) read(r io.Reader, pending []byte) ([]byte, error) {
	const maxRecvDataSize = 128 * 1048576
	var total int
	var b [2048]byte
	var reservedSize uint32

	s := b[:]
	if pending != nil {
		total = len(pending)
		if total > len(b) {
			return nil, errors.New("pending data exceeds limit of 2KB bytes")
		}
		copy(s, pending)
	}

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

	flags := s[4]
	if 0 == (flags & tcpProtocol) {
		return nil, fmt.Errorf("Message is using unsupported protocol version.")
	}

	expectedSize := binary.LittleEndian.Uint32(s[5:9])

	if expectedSize > maxRecvDataSize {
		return nil, fmt.Errorf("Message size %d exceeds the maximum size %d bytes.", expectedSize, maxRecvDataSize)
	}

	if int(expectedSize) < total-headerSize {
		return nil, fmt.Errorf("Message is longer than expected.")
	}

	if 0 != (flags & zlibCompress) {
		reservedSize = binary.LittleEndian.Uint32(s[9:13])
	}

	if int(expectedSize) == total-headerSize {
		if 0 != (flags & zlibCompress) {
			return c.uncompress(s[headerSize:total], reservedSize)
		}
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

	if 0 != (flags & zlibCompress) {
		return c.uncompress(s[:total], reservedSize)
	}
	return s[:total], nil
}

func (c *Connection) uncompress(data []byte, expLen uint32) ([]byte, error) {
	var b bytes.Buffer

	b.Grow(int(expLen))
	z, err := zlib.NewReader(bytes.NewReader(data))
	if nil != err {
		return nil, fmt.Errorf("Unable to uncompress message: '%s'", err)
	}
	len, err := b.ReadFrom(z)
	z.Close()
	if nil != err {
		return nil, fmt.Errorf("Unable to uncompress message: '%s'", err)
	}
	if len != int64(expLen) {
		return nil, fmt.Errorf("Uncompressed message size %d instead of expected %d.", len, expLen)
	}
	return b.Bytes(), nil
}

func (c *Connection) Read() (data []byte, err error) {
	if c.timeoutMode == TimeoutModeShift {
		if err = c.conn.SetReadDeadline(time.Now().Add(c.timeout)); err != nil {
			return
		}
	}

	if c.state == connStateAccept && c.tlsConfig != nil {
		c.state = connStateEstablished

		b := make([]byte, 1)
		var n int
		if n, err = c.conn.Read(b); err != nil {
			return
		}
		if n == 0 {
			return nil, errors.New("connection closed")
		}
		if b[0] != '\x16' {
			// unencrypted connection
			if c.tlsConfig.Accept&tls.ConnUnencrypted == 0 {
				return nil, errors.New("cannot accept unencrypted connection")
			}
			return c.read(c.conn, b)
		}
		if c.tlsConfig.Accept&(tls.ConnPSK|tls.ConnCert) == 0 {
			return nil, errors.New("cannot accept encrypted connection")
		}
		var tlsConn net.Conn
		if tlsConn, err = tls.NewServer(c.conn, c.tlsConfig, b, c.timeout, c.timeoutMode == TimeoutModeShift); err != nil {
			return
		}
		c.conn = tlsConn
	}

	return c.read(c.conn, nil)
}

func (c *Connection) RemoteIP() string {
	addr, _, _ := net.SplitHostPort(c.conn.RemoteAddr().String())
	return addr
}

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

func (l *Listener) Accept(timeout time.Duration, timeoutMode int) (c *Connection, err error) {
	var conn net.Conn
	if conn, err = l.listener.Accept(); err != nil {
		return
	} else {
		c = &Connection{conn: conn, tlsConfig: l.tlsconfig, state: connStateAccept, timeout: timeout,
			timeoutMode: timeoutMode}
	}
	return
}

func (c *Connection) Close() (err error) {
	if c.conn != nil {
		err = c.conn.Close()
	}
	return
}

func (c *Connection) SetCompress(compress bool) {
	c.compress = compress
}

func (c *Listener) Close() (err error) {
	return c.listener.Close()
}

func Exchange(addresses *[]string, localAddr *net.Addr, timeout time.Duration, connect_timeout time.Duration,
	data []byte, args ...interface{}) ([]byte, []error) {
	log.Tracef("connecting to %s [timeout:%s, connection timeout:%s]", *addresses, timeout, connect_timeout)

	var tlsconfig *tls.Config
	var err error
	var errs []error
	var c *Connection

	if len(args) > 0 {
		var ok bool
		if tlsconfig, ok = args[0].(*tls.Config); !ok {
			errs = append(errs, fmt.Errorf("invalid TLS configuration parameter of type %T", args[0]))
			log.Tracef("%s", errs[len(errs)-1])

			return nil, errs
		}
	}

	for i := 0; i < len(*addresses); i++ {
		c, err = open((*addresses)[0], localAddr, timeout, connect_timeout, TimeoutModeFixed, tlsconfig)
		if err == nil {
			break
		}

		errs = append(errs, fmt.Errorf("cannot connect to [%s]: %s", (*addresses)[0], err))
		log.Tracef("%s", errs[len(errs)-1])

		tmp := (*addresses)[0]
		*addresses = (*addresses)[1:]
		*addresses = append(*addresses, tmp)
	}

	if err != nil {
		return nil, errs
	}

	defer c.Close()

	log.Tracef("sending [%s] to [%s]", string(data), (*addresses)[0])

	err = c.Write(data)
	if err != nil {
		errs = append(errs, fmt.Errorf("cannot send to [%s]: %s", (*addresses)[0], err))
		log.Tracef("%s", errs[len(errs)-1])

		return nil, errs
	}

	log.Tracef("receiving data from [%s]", (*addresses)[0])

	b, err := c.Read()
	if err != nil {
		errs = append(errs, fmt.Errorf("cannot receive data from [%s]: %s", (*addresses)[0], err))
		log.Tracef("%s", errs[len(errs)-1])

		return nil, errs
	}
	log.Tracef("received [%s] from [%s]", string(b), (*addresses)[0])

	if len(b) == 0 {
		errs = append(errs, fmt.Errorf("connection closed"))
		log.Tracef("%s", errs[len(errs)-1])

		return nil, errs
	}

	return b, nil
}
