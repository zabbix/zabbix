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

/*
** We use the library go-modbus (goburrow/modbus), which is
** distributed under the terms of the 3-Clause BSD License
** available at https://github.com/goburrow/modbus/blob/master/LICENSE
**/

package modbus

import (
	"encoding/binary"
	"fmt"
	"time"

	named "github.com/BurntSushi/locker"
	"github.com/goburrow/modbus"
	mblib "github.com/goburrow/modbus"
	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

// Plugin -
type Plugin struct {
	plugin.Base
	options PluginOptions
}

// Session struct
type Session struct {
	// Endpoint is a connection string consisting of a protocol scheme, a host address and a port or seral port name and attributes.
	Endpoint string `conf:"optional"`

	// SlaveID of modbus devices.
	SlaveID string `conf:"optional"`

	// Timeout of modbus devices.
	Timeout int `conf:"optional"`
}

// PluginOptions -
type PluginOptions struct {
	// Sessions stores pre-defined named sets of connections settings.
	Sessions map[string]*Session `conf:"optional"`
}

type (
	bits8  uint8
	bits16 uint16
)

// Set of supported modbus connection types
const (
	RTU bits8 = 1 << iota
	ASCII
	TCP
)

// Serial - structure for storing the Modbus connection parameters
type Serial struct {
	PortName string
	Speed    uint32
	DataBits uint8
	Parity   string
	StopBit  uint8
}

// Net - structure for storing the Modbus connection parameters
type Net struct {
	Address string
	Port    uint32
}

// Endianness - byte order of received data
type Endianness struct {
	order  binary.ByteOrder
	middle bits8
}
type mbParams struct {
	ReqType    bits8
	NetAddr    string
	Serial     *Serial
	SlaveID    uint8
	FuncID     uint8
	MemAddr    uint16
	RetType    bits16
	RetCount   uint
	Count      uint16
	Endianness Endianness
	Offset     uint16
}

// Set of supported types
const (
	Bit bits16 = 1 << iota
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

// Set of supported byte orders
const (
	Be bits8 = 1 << iota
	Le
	Mbe
	Mle
)

// Set of supported modbus functions
const (
	ReadCoil     = 1
	ReadDiscrete = 2
	ReadHolding  = 3
	ReadInput    = 4
)

var impl Plugin

func init() {
	err := plugin.RegisterMetrics(
		&impl,
		"Modbus",
		"modbus.get",
		"Returns a JSON array of the requested values, usage: "+
			"modbus.get[endpoint,<slave id>,<function>,<address>,<count>,<type>,<endianness>,<offset>].",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

// Export - main function of plugin
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if key != "modbus.get" {
		return nil, plugin.UnsupportedMetricError
	}

	if len(params) == 0 || len(params) > 8 {
		return nil, fmt.Errorf("Invalid number of parameters:%d", len(params))
	}

	timeout := ctx.Timeout()
	session, ok := p.options.Sessions[params[0]]
	if ok {
		if session.Timeout > 0 {
			timeout = session.Timeout
		}

		if len(session.Endpoint) > 0 {
			params[0] = session.Endpoint
		}

		if len(session.SlaveID) > 0 {
			if len(params) == 1 {
				params = append(params, session.SlaveID)
			} else if len(params[1]) == 0 {
				params[1] = session.SlaveID
			}
		}
	}

	var mbparams *mbParams
	if mbparams, err = parseParams(&params); err != nil {
		return nil, err
	}

	var rawVal []byte
	if rawVal, err = modbusRead(mbparams, timeout); err != nil {
		return nil, err
	}

	if result, err = pack2Json(rawVal, mbparams); err != nil {
		return nil, err
	}

	return result, nil
}

// Configure implements the Configurator interface.
// Initializes configuration structures.
func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	if err := conf.Unmarshal(options, &p.options, true); err != nil {
		p.Errf("cannot unmarshal configuration options: %s", err)
	}
}

// Validate implements the Configurator interface.
// Returns an error if validation of a plugin's configuration is failed.
func (p *Plugin) Validate(options interface{}) error {
	var (
		opts PluginOptions
		err  error
	)

	err = conf.Unmarshal(options, &opts, true)
	if err != nil {
		return err
	}

	for _, s := range opts.Sessions {
		if s.Timeout > 30 || s.Timeout < 0 {
			return fmt.Errorf("Unacceptable session Timeout value:%d", s.Timeout)
		}

		var p mbParams
		var err error
		if p.ReqType, err = getReqType(s.Endpoint); err != nil {
			return err
		}

		switch p.ReqType {
		case RTU, ASCII:
			if p.Serial, err = getSerial(s.Endpoint); err != nil {
				return err
			}
		case TCP:
			if p.NetAddr, err = getNetAddr(s.Endpoint); err != nil {
				return err
			}
		default:
			return fmt.Errorf("Unsupported modbus protocol")
		}

		if p.SlaveID, err = getSlaveID(&[]string{s.SlaveID}, 0, p.ReqType); err != nil {
			return err
		}
	}

	p.Debugf("Config is valid")

	return nil
}

// connecting and receiving data from modbus device
func modbusRead(p *mbParams, timeout int) (results []byte, err error) {
	handler := newHandler(p, timeout)
	var lockName string
	if p.ReqType == TCP {
		lockName = p.NetAddr
	} else {
		lockName = p.Serial.PortName
	}

	named.Lock(lockName)

	switch p.ReqType {
	case TCP:
		err = handler.(*mblib.TCPClientHandler).Connect()
		defer handler.(*mblib.TCPClientHandler).Close()
	case RTU:
		err = handler.(*mblib.RTUClientHandler).Connect()
		defer handler.(*mblib.RTUClientHandler).Close()
	case ASCII:
		err = handler.(*mblib.ASCIIClientHandler).Connect()
		defer handler.(*mblib.ASCIIClientHandler).Close()
	}

	if err != nil {
		named.Unlock(lockName)
		return nil, fmt.Errorf("Unable to connect: %s", err)
	}

	client := mblib.NewClient(handler)
	switch p.FuncID {
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

// make new modbus handler depend on connection type
func newHandler(p *mbParams, timeout int) (handler mblib.ClientHandler) {
	switch p.ReqType {
	case TCP:
		h := mblib.NewTCPClientHandler(p.NetAddr)
		h.SlaveId = p.SlaveID
		h.Timeout = time.Duration(timeout) * time.Second
		handler = h
	case RTU:
		h := modbus.NewRTUClientHandler(p.Serial.PortName)
		h.BaudRate = int(p.Serial.Speed)
		h.DataBits = int(p.Serial.DataBits)
		h.Parity = p.Serial.Parity
		h.StopBits = int(p.Serial.StopBit)
		h.SlaveId = p.SlaveID
		h.Timeout = time.Duration(timeout) * time.Second
		handler = h
	case ASCII:
		h := modbus.NewASCIIClientHandler(p.Serial.PortName)
		h.BaudRate = int(p.Serial.Speed)
		h.DataBits = int(p.Serial.DataBits)
		h.Parity = p.Serial.Parity
		h.StopBits = int(p.Serial.StopBit)
		h.SlaveId = p.SlaveID
		h.Timeout = time.Duration(timeout) * time.Second
		handler = h
	}
	return handler
}
