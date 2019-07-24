package comms

import (
	"bytes"
	"encoding/binary"
	"fmt"
	"io"
	"net"
	"time"
)

type ZbxConnection struct {
	conn net.Conn
}

func (c *ZbxConnection) Open(address string, timeout time.Duration) (err error) {
	c.conn, err = net.DialTimeout("tcp", address, timeout)
	return
}

func write(w io.Writer, data []byte) error {
	var b bytes.Buffer

	b.Grow(len(data) + 13)
	b.Write([]byte{'Z', 'B', 'X', 'D', 0x01})
	err := binary.Write(&b, binary.LittleEndian, uint64(len(data)))
	if nil != err {
		return err
	}
	b.Write(data)
	_, err = w.Write(b.Bytes())

	return err
}

func (c *ZbxConnection) Write(timeout time.Duration, data []byte) error {
	err := c.conn.SetWriteDeadline(time.Now().Add(timeout))
	if nil != err {
		return err
	}
	return write(c.conn, data)
}

func (c *ZbxConnection) WriteString(timeout time.Duration, s string) error {
	return c.Write(timeout, []byte(s))
}

func read(r io.Reader) ([]byte, error) {
	const maxRecvDataSize = 128 * 1048576
	const headerSize = 13
	var total int

	s := make([]byte, 2048)

	for total < headerSize {
		n, err := r.Read(s[total:cap(s)])
		if err != nil {
			return nil, fmt.Errorf("Cannot read message: '%s'", err)
		}

		if n == 0 {
			break
		}

		total += n
	}

	if total < 13 {
		return nil, fmt.Errorf("Message is shorter than expected")
	}

	if !bytes.Equal(s[:4], []byte{'Z', 'B', 'X', 'D'}) {
		return nil, fmt.Errorf("Message is missing header, expected: 'ZBXD' received: '%s'", s[:4])
	}

	if s[4] != 0x01 {
		return nil, fmt.Errorf("Unsupported protocol version, expected: 0x01 received: 0x%02x", s[4])
	}

	expectedSize := binary.LittleEndian.Uint32(s[5:9])

	if expectedSize > maxRecvDataSize {
		return nil, fmt.Errorf("Message size %d exceeds the maximum size %d", expectedSize, maxRecvDataSize)
	}

	if int(expectedSize) < total-headerSize {
		return nil, fmt.Errorf("Message is longer than expected")
	}

	if int(expectedSize) == total-headerSize {
		return s[:total-headerSize], nil
	}

	sTmp := make([]byte, expectedSize)
	if total > headerSize {
		copy(sTmp, s[headerSize:total])
	}
	s = sTmp
	total = total - headerSize

	for total < int(expectedSize) {
		n, err := r.Read(s[total:cap(s)])
		if err != nil {
			return nil, err
		}

		if n == 0 {
			break
		}

		total += n
	}

	return s[:total], nil
}

func (c *ZbxConnection) Read(timeout time.Duration) ([]byte, error) {
	return read(c.conn)
}
