/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

package modbus

import (
	"bytes"
	"encoding/binary"
	"encoding/json"
	"fmt"
	"math"
)

func pack2Json(val []byte, p *MBParams) (jdata interface{}, err error) {

	if 0 != (p.RetType & (Bit | Uint8)) {
		if jdata, err = json.Marshal(val); err != nil {
			return nil, fmt.Errorf("Unable to create json: %s", err)
		}
		return jdata, nil
	}

	var typeSize int
	switch p.RetType {
	case Uint64, Double:
		typeSize = 8
	case Int32, Uint32, Float:
		typeSize = 4
	case Int16, Uint16:
		typeSize = 2
	default:
		typeSize = 1
	}

	var v interface{}
	arraySize := uint(math.Ceil(float64(len(val) / typeSize)))
	switch p.RetType {
	case Uint64:
		v = make([]uint64, arraySize)
	case Double:
		v = make([]float64, arraySize)
	case Int32:
		v = make([]int32, arraySize)
	case Uint32:
		v = make([]uint32, arraySize)
	case Float:
		v = make([]float32, arraySize)
	case Int16:
		v = make([]int16, arraySize)
	case Uint16:
		v = make([]uint16, arraySize)
	case Int8:
		v = make([]int8, arraySize)
	}

	r := bytes.NewReader(val)
	binary.Read(r, p.Endianness.order, &v)

	if typeSize > 2 && 0 != p.Endianness.middle {
		v = middlePack(v, p.RetType)
	}

	if jdata, err = json.Marshal(v); err != nil {
		return nil, fmt.Errorf("Unable to create json: %s", err)
	}
	return jdata, nil
}

func middlePack(v interface{}, rt Bits16) interface{} {
	buf := new(bytes.Buffer)
	switch rt {
	case Uint64:
		for i, num := range v.([]uint64) {
			binary.Write(buf, binary.BigEndian, &num)
			bs := buf.Bytes()
			bs = []byte{bs[2], bs[3], bs[0], bs[1], bs[6], bs[7], bs[4], bs[5]}
			rd := bytes.NewReader(bs)
			binary.Read(rd, binary.BigEndian, &num)
			v.([]uint64)[i] = num
			buf.Reset()
		}
	case Double:
		for i, num := range v.([]float64) {
			binary.Write(buf, binary.BigEndian, &num)
			bs := buf.Bytes()
			bs = []byte{bs[2], bs[3], bs[0], bs[1], bs[6], bs[7], bs[4], bs[5]}
			rd := bytes.NewReader(bs)
			binary.Read(rd, binary.BigEndian, &num)
			v.([]float64)[i] = num
			buf.Reset()
		}
	case Int32:
		for i, num := range v.([]int32) {
			binary.Write(buf, binary.BigEndian, &num)
			bs := buf.Bytes()
			bs = []byte{bs[2], bs[3], bs[0], bs[1]}
			rd := bytes.NewReader(bs)
			binary.Read(rd, binary.BigEndian, &num)
			v.([]int32)[i] = num
			buf.Reset()
		}
	case Uint32:
		for i, num := range v.([]uint32) {
			binary.Write(buf, binary.BigEndian, &num)
			bs := buf.Bytes()
			bs = []byte{bs[2], bs[3], bs[0], bs[1]}
			rd := bytes.NewReader(bs)
			binary.Read(rd, binary.BigEndian, &num)
			v.([]uint32)[i] = num
			buf.Reset()
		}
	case Float:
		for i, num := range v.([]float32) {
			binary.Write(buf, binary.BigEndian, &num)
			bs := buf.Bytes()
			bs = []byte{bs[2], bs[3], bs[0], bs[1]}
			rd := bytes.NewReader(bs)
			binary.Read(rd, binary.BigEndian, &num)
			v.([]float32)[i] = num
			buf.Reset()
		}
	}
	return v
}
