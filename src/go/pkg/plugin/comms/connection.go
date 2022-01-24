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

package comms

import (
	"bytes"
	"encoding/binary"
	"encoding/json"
	"fmt"
	"net"
)

const JSONType = uint32(1)
const headerTypeLen = 4
const headerDataLen = 4

func Read(conn net.Conn) (dataType uint32, requestData []byte, err error) {
	reqByteType := make([]byte, headerTypeLen)
	reqByteLen := make([]byte, headerDataLen)
	n, err := conn.Read(reqByteType)
	if err != nil {
		return
	}

	if n < headerTypeLen {
		err = fmt.Errorf(
			"incomplete protocol header type value, %d bytes read, must be 4 bytes",
			n,
		)

		return
	}

	if JSONType != binary.LittleEndian.Uint32(reqByteType) {
		err = fmt.Errorf("only json data type (%d) supported", JSONType)

		return
	}

	n, err = conn.Read(reqByteLen)
	if err != nil {
		return
	}

	if n < headerDataLen {
		err = fmt.Errorf(
			"incomplete protocol header length value, %d bytes read, must be 4 bytes",
			n,
		)

		return
	}

	reqLen := int32(binary.LittleEndian.Uint32(reqByteLen))
	data := make([]byte, reqLen)

	n, err = conn.Read(data)
	if err != nil {
		return
	}

	if n < int(reqLen) {
		err = fmt.Errorf(
			"incomplete protocol body value, %d bytes read, must be %d bytes",
			n,
			reqLen,
		)

		return
	}

	var c Common
	if err := json.Unmarshal(data, &c); err != nil {
		return 0, nil, err
	}

	return c.Type, data, nil
}

func Write(conn net.Conn, in interface{}) (err error) {
	reqBytes, err := json.Marshal(in)
	if err != nil {
		return
	}

	buf := new(bytes.Buffer)
	err = binary.Write(buf, binary.LittleEndian, JSONType)
	if err != nil {
		return
	}

	err = binary.Write(buf, binary.LittleEndian, uint32(len(reqBytes)))
	if err != nil {
		return
	}

	_, err = buf.Write(reqBytes)
	if err != nil {
		return
	}

	if _, err = conn.Write(buf.Bytes()); err != nil {
		return
	}

	return
}
