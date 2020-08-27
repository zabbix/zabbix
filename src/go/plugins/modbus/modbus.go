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
	"time"

	"encoding/binary"

	named "github.com/BurntSushi/locker"
	"github.com/goburrow/modbus"
	mblib "github.com/goburrow/modbus"
	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/plugin"
)

// Plugin -
type Plugin struct {
	plugin.Base
	options PluginOptions
}

type PluginOptions struct {
	// Timeout is the maximum time for waiting when a request has to be done. Default value equals the global timeout.
	Timeout int `conf:"optional,range=1:30"`
}

type Bits8 uint8
type Bits16 uint16

const (
	Rtu Bits8 = 1 << iota
	Ascii
	Tcp
)

type Serial struct {
	PortName string
	Speed    uint32
	DataBits uint8
	Parity   string
	StopBit  uint8
}

type Net struct {
	Address string
	Port    uint32
}

type Endianness struct {
	order  binary.ByteOrder
	middle Bits8
}
type MBParams struct {
	ReqType    Bits8
	NetAddr    string
	Serial     *Serial
	SlaveId    uint8
	FuncId     uint8
	MemAddr    uint16
	RetType    Bits16
	RetCount   uint
	Count      uint16
	Endianness Endianness
	Offset     uint16
}

const (
	Bit Bits16 = 1 << iota
	Int8
	Uint8
	Int16
	Uint16
	Int32
	Uint32
	Float
	Uint64
	Double
)

const (
	Be Bits8 = 1 << iota
	Le
	Mbe
	Mle
)

const (
	ReadCoil     = 1
	ReadDiscrete = 2
	ReadHolding  = 3
	ReadInput    = 4
)

var impl Plugin

func init() {
	plugin.RegisterMetrics(&impl, "modbus",
		"modbus.get", "Returns a JSON array of the requested values, usage: modbus.get[endpoint,<slave id>,<function>,<address>,<count>,<type>,<endianess>,<offset>].")
}

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {

	if key != "modbus.get" {
		return nil, plugin.UnsupportedMetricError
	}

	var mbparams *MBParams
	if mbparams, err = parseParams(&params); err != nil {
		return nil, err
	}

	var raw_val []byte
	if raw_val, err = mbRead(mbparams, p.options.Timeout); err != nil {
		return nil, err
	}

	if result, err = pack2Json(raw_val, mbparams); err != nil {
		return nil, err
	}

	return result, nil
}

// Configure implements the Configurator interface.
// Initializes configuration structures.
func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	if err := conf.Unmarshal(options, &p.options); err != nil {
		p.Errf("cannot unmarshal configuration options: %s", err)
	}

	if p.options.Timeout == 0 {
		p.options.Timeout = global.Timeout
	}
}

// Validate implements the Configurator interface.
// Returns an error if validation of a plugin's configuration is failed.
func (p *Plugin) Validate(options interface{}) error {
	var (
		opts PluginOptions
		err  error
	)

	if err = conf.Unmarshal(options, &opts); err != nil {
		return err
	}

	if opts.Timeout > 3600 || opts.Timeout < 0 {
		return fmt.Errorf("Unacceptable Timeout value:%d", opts.Timeout)
	}

	return nil
}

func mbRead(p *MBParams, timeout int) (results []byte, err error) {
	handler := newHandler(p, timeout)
	var lockName string
	if p.ReqType == Tcp {
		lockName = p.NetAddr
	} else {
		lockName = p.Serial.PortName
	}

	named.Lock(lockName)

	switch p.ReqType {
	case Tcp:
		err = handler.(*mblib.TCPClientHandler).Connect()
		defer handler.(*mblib.TCPClientHandler).Close()
	case Rtu:
		err = handler.(*mblib.RTUClientHandler).Connect()
		defer handler.(*mblib.RTUClientHandler).Close()
	case Ascii:
		err = handler.(*mblib.ASCIIClientHandler).Connect()
		defer handler.(*mblib.ASCIIClientHandler).Close()
	}

	if err != nil {
		named.Unlock(lockName)
		return nil, fmt.Errorf("Unable to connect: %s", err)
	}

	client := modbus.NewClient(handler)
	switch p.FuncId {
	case ReadCoil:
		results, err = client.ReadCoils(p.MemAddr, p.Count)
	case ReadDiscrete:
		results, err = client.ReadDiscreteInputs(p.MemAddr, p.Count)
	case ReadHolding:
		results, err = client.ReadHoldingRegisters(p.MemAddr, p.Count)
	case ReadInput:
		results, err = client.ReadInputRegisters(p.MemAddr, p.Count)
	}

	named.Unlock(lockName)

	if err != nil {
		return nil, fmt.Errorf("Unable to read: %s", err)
	} else if len(results) == 0 {
		return nil, fmt.Errorf("Unable to read data")
	}

	return results, nil
}

func newHandler(p *MBParams, timeout int) (handler mblib.ClientHandler) {
	switch p.ReqType {
	case Tcp:
		h := mblib.NewTCPClientHandler(p.NetAddr)
		h.SlaveId = p.SlaveId
		h.Timeout = time.Duration(timeout) * time.Second
		handler = h
	case Rtu:
		h := modbus.NewRTUClientHandler(p.Serial.PortName)
		h.BaudRate = int(p.Serial.Speed)
		h.DataBits = int(p.Serial.DataBits)
		h.Parity = p.Serial.Parity
		h.StopBits = int(p.Serial.StopBit)
		h.SlaveId = p.SlaveId
		h.Timeout = time.Duration(timeout) * time.Second
		handler = h
	case Ascii:
		h := modbus.NewASCIIClientHandler(p.Serial.PortName)
		h.BaudRate = int(p.Serial.Speed)
		h.DataBits = int(p.Serial.DataBits)
		h.Parity = p.Serial.Parity
		h.StopBits = int(p.Serial.StopBit)
		h.SlaveId = p.SlaveId
		h.Timeout = time.Duration(timeout) * time.Second
		handler = h
	}
	return handler
}
