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

package modbus

import (
	"bytes"
	"encoding/binary"
	"encoding/json"
	"fmt"
)

// main data transformation
func pack2Json(val []byte, p *mbParams) (jdata interface{}, err error) {

	if p.RetType == Bit {
		ar := getArr16(p.RetType, uint(p.Count), val)
		if p.RetCount == 1 {
			return getFirst(ar), nil
		}

		if len(ar) < int(p.Offset) {
			return nil, fmt.Errorf("Wrong length of received data: %d", len(ar))
		}

		if p.Offset > 0 {
			ar = ar[p.Offset:]
		}

		jd, jerr := json.Marshal(ar)
		if jerr != nil {
			return nil, fmt.Errorf("Unable to create json: %s", jerr)
		}
		return string(jd), nil
	}

	if len(val) < int(p.Offset*2) {
		return nil, fmt.Errorf("Wrong length of received data: %d", len(val))
	}

	if p.Offset > 0 {
		val = val[p.Offset*2:]
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

	var arr interface{}
	if typeSize == 1 && p.Endianness.order == binary.LittleEndian {
		arr = swapPairByte(val, p.RetType, p.RetCount)
	} else {
		arr = makeRetArray(p.RetType, p.RetCount)
		r := bytes.NewReader(val)
		if err = binary.Read(r, p.Endianness.order, arr); err != nil {
			return nil, fmt.Errorf("Unable to convert returned data: %w", err)
		}
	}

	if typeSize > 2 && 0 != p.Endianness.middle {
		arr = middlePack(arr, p.RetType)
	}

	if p.RetCount == 1 {
		return getFirst(arr), nil
	}

	if p.RetType == Uint8 {
		arr = getArr16(p.RetType, p.RetCount, arr.([]byte))
	}

	jd, jerr := json.Marshal(arr)
	if jerr != nil {
		return nil, fmt.Errorf("Unable to create json: %s", jerr)
	}
	return string(jd), nil
}

func swapPairByte(v []byte, retType bits16, retCount uint) (ret interface{}) {
	switch retType {
	case Int8:
		ret = make([]int8, len(v))
		for i := 0; i < len(v)-1; i += 2 {
			ret.([]int8)[i] = int8(v[i+1])
			ret.([]int8)[i+1] = int8(v[i])
		}
		ret = ret.([]int8)[:retCount]
	case Uint8:
		ret = make([]byte, len(v))
		for i := 0; i < len(v)-1; i += 2 {
			ret.([]byte)[i] = v[i+1]
			ret.([]byte)[i+1] = v[i]
		}
		ret = ret.([]byte)[:retCount]
	}
	return ret
}

func getArr16(retType bits16, retCount uint, val []byte) []uint16 {
	ar := make([]uint16, retCount)
	if retType == Bit {
		for i := range val {
			for j := 0; j < 8; j++ {
				ar[i*8+j] = uint16(val[i] & (1 << j) >> j)
				if retCount--; retCount == 0 {
					return ar
				}
			}
		}
	} else {
		for i := range val {
			ar[i] = uint16(val[i])
		}
	}

	return ar
}

func middlePack(v interface{}, rt bits16) interface{} {
	buf := new(bytes.Buffer)
	switch rt {
	case Uint64:
		for i, num := range v.([]uint64) {
			binary.Write(buf, binary.BigEndian, &num)
			bs := buf.Bytes()
			bs = []byte{bs[1], bs[0], bs[3], bs[2], bs[5], bs[4], bs[7], bs[6]}
			rd := bytes.NewReader(bs)
			binary.Read(rd, binary.BigEndian, &num)
			v.([]uint64)[i] = num
			buf.Reset()
		}
	case Double:
		for i, num := range v.([]float64) {
			binary.Write(buf, binary.BigEndian, &num)
			bs := buf.Bytes()
			bs = []byte{bs[1], bs[0], bs[3], bs[2], bs[5], bs[4], bs[7], bs[6]}
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

func makeRetArray(retType bits16, arraySize uint) (v interface{}) {
	switch retType {
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
	case Uint8:
		v = make([]byte, arraySize)
	}
	return v
}

func getFirst(v interface{}) interface{} {
	switch v := v.(type) {
	case []uint64:
		return v[0]
	case []float64:
		return v[0]
	case []uint32:
		return v[0]
	case []int32:
		return v[0]
	case []float32:
		return v[0]
	case []uint16:
		return v[0]
	case []int16:
		return v[0]
	case []int8:
		return v[0]
	case []byte:
		return v[0]
	}
	return nil
}
