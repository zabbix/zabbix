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
	"fmt"
	"net"
	"net/url"
	"strconv"
	"strings"
)

func parseParams(params *[]string) (*MBParams, error) {
	if len(*params) == 0 || len(*params) > 7 {
		return nil, fmt.Errorf("Invalid number of parameters:%d", len(*params))
	}

	var p MBParams
	var err error

	if p.ReqType, err = getReqType((*params)[0]); err != nil {
		return nil, err
	}

	switch p.ReqType {
	case Rtu, Ascii:
		if p.Serial, err = getSerial((*params)[0]); err != nil {
			return nil, err
		}
	case Tcp:
		if p.NetAddr, err = getNetAddr((*params)[0]); err != nil {
			return nil, err
		}
	default:
		return nil, fmt.Errorf("Unsupported modbus protocol.")
	}

	if p.SlaveId, err = getSlaveId(params, 1, p.ReqType); err != nil {
		return nil, err
	}
	if p.FuncId, err = getFuncId(params, 2); err != nil {
		return nil, err
	}
	if p.MemAddr, p.FuncId, err = getMemAddr(params, 3, p.FuncId); err != nil {
		return nil, err
	}

	if p.RetType, err = getRetType(params, 5, p.FuncId); err != nil {
		return nil, err
	}

	if p.Count, p.RetCount, err = getCount(params, 4, p.RetType); err != nil {
		return nil, err
	}

	if p.Endianness, err = getEndianness(params, 6); err != nil {
		return nil, err
	}

	if p.Count, p.Offset, err = getOffset(params, 7, p.Count); err != nil {
		return nil, err
	}

	return &p, nil
}

func getReqType(v string) (reqType Bits8, err error) {
	pos := strings.Index(v, "://")
	if pos < 0 {
		return 0, fmt.Errorf("Unsupported endpoint format.")
	}
	switch v[:pos] {
	case "rtu":
		reqType = Rtu
	case "ascii":
		reqType = Ascii
	case "tcp":
		reqType = Tcp
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
		return &a, nil
	}

	var speed uint64
	val = val[inx+1:]
	if inx = strings.Index(val, ":"); inx < 0 {
		speed, err = strconv.ParseUint(val, 10, 32)
		a.Speed = uint32(speed)
		return &a, err
	} else if speed, err = strconv.ParseUint(val[:inx], 10, 32); err != nil {
		return nil, err
	}
	a.Speed = uint32(speed)

	val = val[inx+1:]
	if len(val) != 3 {
		return nil, fmt.Errorf("Unsupported params format of serial line.")
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

	if h, p, err1 := net.SplitHostPort(checkAddr); err1 != nil {
		return "", fmt.Errorf("address \"%s\": %s", val, err1)
	} else {
		netAddr = net.JoinHostPort(strings.TrimSpace(h), strings.TrimSpace(p))
	}

	return netAddr, nil
}

func getSlaveId(p *[]string, n int, reqType Bits8) (slaveId uint8, err error) {
	v := ""

	if len(*p) > n {
		v = strings.TrimSpace((*p)[n])
	}
	if len(v) == 0 {
		if reqType == Tcp {
			return 255, nil
		}
		return 0, fmt.Errorf("Unsupported empty value of slave id for serial line")
	}
	var val uint64
	if val, err = strconv.ParseUint(v, 10, 8); err != nil {
		return 0, err
	}
	slaveId = uint8(val)

	if 0 != (reqType&(Rtu|Ascii)) && (slaveId == 0 || slaveId > 247) {
		return 0, fmt.Errorf("Unsupported slave id value for serial line:%d", slaveId)
	}

	return slaveId, nil
}

func getFuncId(p *[]string, n int) (funcId uint8, err error) {
	v := ""

	if len(*p) > n {
		v = strings.TrimSpace((*p)[n])
	}
	if len(v) == 0 {
		return 0, nil
	}

	var val uint64
	if val, err = strconv.ParseUint(v, 10, 8); err != nil {
		return 0, err
	}
	funcId = uint8(val)

	if funcId == 0 || funcId > 4 {
		return 0, fmt.Errorf("Unsupported modbus opration:%d", funcId)
	}

	return funcId, nil
}

func getMemAddr(p *[]string, n int, fid uint8) (memAddr uint16, funcId uint8, err error) {
	v := "00001"

	if len(*p) > n {
		v = strings.TrimSpace((*p)[n])
	}
	if len(v) == 0 {
		return 0, fid, fmt.Errorf("Unsupported empty modbus address")
	}
	var val uint64
	if val, err = strconv.ParseUint(v, 10, 16); err != nil {
		return 0, fid, err
	}
	memAddr = uint16(val)

	if fid != 0 {
		return memAddr, fid, nil
	}

	if memAddr >= 50000 || memAddr == 0 {
		return 0, fid, fmt.Errorf("Unsupported modbus address for empty function:%d", memAddr)
	}
	funcId = uint8(memAddr / 10000)

	switch funcId {
	case 0:
		funcId = 1
	case 1:
		funcId = 2
		memAddr = memAddr - 10000
	case 3:
		memAddr = memAddr - 30000
	case 4:
		memAddr = memAddr - 40000
	default:
		return 0, fid, fmt.Errorf("Unsupported modbus function for address:%d", memAddr)
	}

	return memAddr, funcId, nil
}

func getRetType(p *[]string, n int, funcId uint8) (retType Bits16, err error) {
	v := ""

	if len(*p) > n {
		v = strings.TrimSpace((*p)[n])
	}
	if len(v) == 0 {
		if funcId == ReadCoil || funcId == ReadDiscrete {
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
	if (funcId == ReadCoil || funcId == ReadDiscrete) && retType != Bit {
		return 0, fmt.Errorf("Unsupported type for Read Coil and Read Discrete Input:%s", v)
	}
	return retType, nil
}

func getCount(p *[]string, n int, retType Bits16) (count uint16, retCount uint, err error) {
	v := "1"

	if len(*p) > n {
		v = strings.TrimSpace((*p)[n])
	}
	if len(v) == 0 {
		v = "1"
	}

	var val uint64
	if val, err = strconv.ParseUint(v, 10, 32); err != nil {
		return 0, 0, err
	} else if val == 0 {
		return 0, 0, fmt.Errorf("Unsupported zero as data length")
	}
	retCount = uint(val)

	var realCount uint
	switch retType {
	case Int8, Uint8:
		realCount = uint((retCount + 1) / 2)
	case Int32, Uint32:
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

func getEndianness(p *[]string, n int) (endianness Bits8, err error) {
	v := "be"

	if len(*p) > n {
		v = strings.TrimSpace((*p)[n])
	}
	if len(v) == 0 {
		v = "be"
	}
	switch v {
	case "be":
		endianness = Be
	case "le":
		endianness = Le
	case "mbe":
		endianness = Mbe
	case "mle":
		endianness = Mle
	default:
		return 0, fmt.Errorf("Unsupported endianness of data:%s", v)
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
		return c, 0, err
	}
	offset = uint16(val)
	if (offset + c) > 65535 {
		return c, 0, fmt.Errorf("Unsupported common data length and offset:%d", offset+c)
	}
	count = c + offset
	return count, offset, nil
}
