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
	"encoding/binary"
	"fmt"
	"net"
	"net/url"
	"runtime"
	"strconv"
	"strings"
)

// main parsing
func parseParams(params *[]string) (*mbParams, error) {
	var p mbParams
	var err error

	if p.ReqType, err = getReqType((*params)[0]); err != nil {
		return nil, err
	}

	switch p.ReqType {
	case RTU, ASCII:
		if p.Serial, err = getSerial((*params)[0]); err != nil {
			return nil, err
		}
	case TCP:
		if p.NetAddr, err = getNetAddr((*params)[0]); err != nil {
			return nil, err
		}
	default:
		return nil, fmt.Errorf("Unsupported modbus protocol")
	}

	if p.SlaveID, err = getSlaveID(params, 1, p.ReqType); err != nil {
		return nil, err
	}

	if p.FuncID, err = getFuncID(params, 2); err != nil {
		return nil, err
	}

	if p.MemAddr, p.FuncID, err = getMemAddr(params, 3, p.FuncID); err != nil {
		return nil, err
	}

	if p.RetType, err = getRetType(params, 5, p.FuncID); err != nil {
		return nil, err
	}

	if p.Count, p.RetCount, err = getCount(params, 4, p.RetType); err != nil {
		return nil, err
	}

	if p.Endianness, err = getEndianness(params, 6, p.RetType); err != nil {
		return nil, err
	}

	if p.Count, p.Offset, err = getOffset(params, 7, p.Count); err != nil {
		return nil, err
	}

	return &p, nil
}

func getReqType(v string) (reqType bits8, err error) {
	pos := strings.Index(v, "://")
	if pos < 0 {
		return 0, fmt.Errorf("Unsupported endpoint format")
	}
	switch v[:pos] {
	case "rtu":
		reqType = RTU
	case "ascii":
		reqType = ASCII
	case "tcp":
		reqType = TCP
	default:
		reqType = 0
	}
	return reqType, nil
}

func getSerial(v string) (addr *Serial, err error) {
	a := Serial{Speed: 115200, DataBits: 8, Parity: "N", StopBit: 1}

	val := v[strings.Index(v, "://")+3:]
	inx := strings.Index(val, ":")
	if inx < 0 {
		a.PortName = val
		if runtime.GOOS != "windows" && strings.Index(a.PortName, "/") < 0 {
			a.PortName = "/dev/" + a.PortName
		}
		return &a, nil
	}
	a.PortName = val[:inx]
	if runtime.GOOS != "windows" && strings.Index(a.PortName, "/") < 0 {
		a.PortName = "/dev/" + a.PortName
	}

	var speed uint64
	val = val[inx+1:]
	if inx = strings.Index(val, ":"); inx < 0 {
		if speed, err = strconv.ParseUint(val, 10, 32); err != nil {
			return &a, fmt.Errorf("Unsupported speed value: %w", err)
		}
		a.Speed = uint32(speed)
		return &a, nil
	} else if speed, err = strconv.ParseUint(val[:inx], 10, 32); err != nil {
		return nil, fmt.Errorf("Unsupported speed value: %w", err)
	}
	a.Speed = uint32(speed)

	val = val[inx+1:]
	if len(val) != 3 {
		return nil, fmt.Errorf("Unsupported params format of serial line")
	}

	switch val[0] {
	case '5':
		a.DataBits = 5
	case '6':
		a.DataBits = 6
	case '7':
		a.DataBits = 7
	case '8':
		a.DataBits = 8
	default:
		return nil, fmt.Errorf("Unsupported data bits value of serial line:" + val[:1])
	}

	switch val[1] {
	case 'n':
		a.Parity = "N"
	case 'e':
		a.Parity = "E"
	case 'o':
		a.Parity = "O"
	default:
		return nil, fmt.Errorf("Unsupported parity value of serial line:" + val[1:2])
	}

	switch val[2] {
	case '1':
		a.StopBit = 1
	case '2':
		a.StopBit = 2
	default:
		return nil, fmt.Errorf("Unsupported stop bit value of serial line:" + val[2:])
	}

	return &a, nil
}

func getNetAddr(v string) (netAddr string, err error) {
	val := v[strings.Index(v, "://")+3:]

	u := url.URL{Host: val}
	ip := net.ParseIP(val)
	if nil == ip && 0 == len(strings.TrimSpace(u.Hostname())) {
		return "", fmt.Errorf("address \"%s\": empty value", val)
	}

	var checkAddr string
	if nil != ip {
		checkAddr = net.JoinHostPort(val, "502")
	} else if 0 == len(u.Port()) {
		checkAddr = net.JoinHostPort(u.Hostname(), "502")
	} else {
		checkAddr = val
	}

	var (
		h string
		p string
	)
	if h, p, err = net.SplitHostPort(checkAddr); err != nil {
		return "", fmt.Errorf("address \"%s\": %s", val, err)
	}
	netAddr = net.JoinHostPort(strings.TrimSpace(h), strings.TrimSpace(p))

	return netAddr, nil
}

func getSlaveID(p *[]string, n int, reqType bits8) (slaveID uint8, err error) {
	v := ""

	if len(*p) > n {
		v = strings.TrimSpace((*p)[n])
	}
	if len(v) == 0 {
		if reqType == TCP {
			return 255, nil
		}
		return 1, nil
	}
	var val uint64
	if val, err = strconv.ParseUint(v, 10, 8); err != nil {
		return 0, fmt.Errorf("Unsupported slave id for serial line: %w", err)
	}
	slaveID = uint8(val)

	if 0 != (reqType&(RTU|ASCII)) && (slaveID == 0 || slaveID > 247) {
		return 0, fmt.Errorf("Unsupported slave id value for serial line:%d", slaveID)
	}

	return slaveID, nil
}

func getFuncID(p *[]string, n int) (funcID uint8, err error) {
	v := ""

	if len(*p) > n {
		v = strings.TrimSpace((*p)[n])
	}
	if len(v) == 0 {
		return 0, nil
	}

	var val uint64
	if val, err = strconv.ParseUint(v, 10, 8); err != nil {
		return 0, fmt.Errorf("Operation id: %w", err)
	}
	funcID = uint8(val)

	if funcID == 0 || funcID > 4 {
		return 0, fmt.Errorf("Unsupported modbus operation:%d", funcID)
	}

	return funcID, nil
}

func getMemAddr(p *[]string, n int, fid uint8) (memAddr uint16, funcID uint8, err error) {
	var v string

	if len(*p) > n && strings.TrimSpace((*p)[n]) != "" {
		v = strings.TrimSpace((*p)[n])
	}
	if len(v) == 0 {
		switch fid {
		case 0:
			v = "00001"
		default:
			v = "0"
		}
	}

	if fid == 0 && len(v) != 5 {
		return 0, fid, fmt.Errorf("Unsupported modbus address length for empty function:%d", len(v))
	}

	var val uint64
	if val, err = strconv.ParseUint(v, 10, 16); err != nil {
		return 0, fid, fmt.Errorf("Unsupported modbus address: %w", err)
	}
	memAddr = uint16(val)

	if fid != 0 {
		return memAddr, fid, nil
	}

	if memAddr >= 50000 || memAddr == 0 {
		return 0, fid, fmt.Errorf("Unsupported modbus address for empty function:%d", memAddr)
	}
	funcID = uint8(memAddr / 10000)

	switch funcID {
	case 0:
		funcID = 1
	case 1:
		funcID = 2
		memAddr = memAddr - 10000
	case 3:
		funcID = 4
		memAddr = memAddr - 30000
	case 4:
		funcID = 3
		memAddr = memAddr - 40000
	default:
		return 0, fid, fmt.Errorf("Unsupported modbus function for address:%d", memAddr)
	}

	return memAddr - 1, funcID, nil
}

func getRetType(p *[]string, n int, funcID uint8) (retType bits16, err error) {
	v := ""

	if len(*p) > n {
		v = strings.TrimSpace((*p)[n])
	}
	if len(v) == 0 {
		if funcID == ReadCoil || funcID == ReadDiscrete {
			return Bit, nil
		}
		return Uint16, nil
	}
	switch v {
	case "bit":
		retType = Bit
	case "int8":
		retType = Int8
	case "uint8":
		retType = Uint8
	case "int16":
		retType = Int16
	case "uint16":
		retType = Uint16
	case "int32":
		retType = Int32
	case "uint32":
		retType = Uint32
	case "float":
		retType = Float
	case "uint64":
		retType = Uint64
	case "double":
		retType = Double
	default:
		return 0, fmt.Errorf("Unsupported type:%s", v)
	}
	if (funcID == ReadCoil || funcID == ReadDiscrete) && retType != Bit {
		return 0, fmt.Errorf("Unsupported type for Read Coil and Read Discrete Input:%s", v)
	}
	if (funcID == ReadHolding || funcID == ReadInput) && retType == Bit {
		return 0, fmt.Errorf("Unsupported type for Read Holding and Read Input registers:%s", v)
	}
	return retType, nil
}

func getCount(p *[]string, n int, retType bits16) (count uint16, retCount uint, err error) {
	v := "1"

	if len(*p) > n {
		v = strings.TrimSpace((*p)[n])
	}
	if len(v) == 0 {
		v = "1"
	}

	var val uint64
	if val, err = strconv.ParseUint(v, 10, 32); err != nil {
		return 0, 0, fmt.Errorf("Unsupported data length: %w", err)
	} else if val == 0 {
		return 0, 0, fmt.Errorf("Unsupported zero as data length")
	}
	retCount = uint(val)

	var realCount uint
	switch retType {
	case Int8, Uint8:
		realCount = uint((retCount + 1) / 2)
	case Int32, Uint32, Float:
		realCount = uint(retCount * 2)
	case Double, Uint64:
		realCount = retCount * 4
	default:
		realCount = retCount
	}
	if realCount > 65535 {
		return 0, 0, fmt.Errorf("Unsupported data type with required data count:%d", retCount)
	}
	count = uint16(realCount)
	return count, retCount, nil
}

func getEndianness(p *[]string, n int, retType bits16) (endianness Endianness, err error) {
	v := "be"

	if len(*p) > n {
		v = strings.TrimSpace((*p)[n])
	}
	if len(v) == 0 {
		v = "be"
	}
	switch v {
	case "be":
		endianness.order = binary.BigEndian
		endianness.middle = 0
	case "le":
		endianness.order = binary.LittleEndian
		endianness.middle = 0
	case "mbe":
		endianness.order = binary.BigEndian
		endianness.middle = Mbe
	case "mle":
		endianness.order = binary.LittleEndian
		endianness.middle = Mle
	default:
		return endianness, fmt.Errorf("Unsupported endianness of data:%s", v)
	}

	if retType == Bit && (endianness.order != binary.BigEndian || endianness.middle != 0) {
		return endianness, fmt.Errorf("Unsupported endianness with required data type:%s", v)
	}

	if 0 != (retType&(Uint8|Int8|Uint16|Int16)) && endianness.middle != 0 {
		return endianness, fmt.Errorf("Unsupported middle-endian with required data type:%s", v)
	}

	return endianness, nil
}

func getOffset(p *[]string, n int, c uint16) (count uint16, offset uint16, err error) {
	v := ""

	if len(*p) > n {
		v = strings.TrimSpace((*p)[n])
	}
	if len(v) == 0 {
		return c, 0, nil
	}
	var val uint64
	if val, err = strconv.ParseUint(v, 10, 16); err != nil {
		return c, 0, fmt.Errorf("Unsupported offset: %w", err)
	}
	offset = uint16(val)
	if (offset + c) > 65535 {
		return c, 0, fmt.Errorf("Unsupported common data length and offset:%d", offset+c)
	}
	count = c + offset
	return count, offset, nil
}
