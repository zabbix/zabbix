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

package udp

import (
	"bytes"
	"errors"
	"math"
	"net"
	"strconv"
	"time"

	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/log"
	"zabbix.com/pkg/plugin"
)

const (
	errorInvalidFirstParam  = "Invalid first parameter."
	errorInvalidSecondParam = "Invalid second parameter."
	errorInvalidThirdParam  = "Invalid third parameter."
	errorInvalidFourthParam = "Invalid fourth parameter."
	errorInvalidFifthParam  = "Invalid fifth parameter."
	errorTooManyParams      = "Too many parameters."
	errorUnsupportedMetric  = "Unsupported metric."
)

const (
	ntpVersion          = 3
	ntpModeServer       = 4
	ntpTransmitRspStart = 24
	ntpTransmitRspEnd   = 32
	ntpTransmitReqStart = 40
	ntpPacketSize       = 48
	ntpEpochOffset      = 2208988800.0
	ntpScale            = 4294967296.0
)

type Options struct {
	plugin.SystemOptions `conf:"optional,name=System"`
	Timeout              time.Duration `conf:"optional,range=1:30"`
}

// Plugin -
type Plugin struct {
	plugin.Base
	options Options
}

var impl Plugin

func (p *Plugin) createRequest(req []byte) {
	// NTP configure request settings by specifying the first byte as
	// 00 011 011 (or 0x1B)
	// |  |   +-- client mode (3)
	// |  + ----- version (3)
	// + -------- leap year indicator, 0 no warning
	req[0] = 0x1B

	transmitTime := time.Now().Unix() + ntpEpochOffset
	f := float64(transmitTime) / ntpScale

	for i := 0; i < 8; i++ {
		f *= 256.0
		k := int64(f)

		if k >= 256 {
			k = 255
		}

		req[ntpTransmitReqStart+i] = byte(k)
		f -= float64(k)
	}
}

func (p *Plugin) validateResponse(rsp []byte, ln int, req []byte) int {
	if ln != ntpPacketSize {
		log.Debugf("invalid response size: %d", ln)
		return 0
	}

	if !bytes.Equal(req[ntpTransmitReqStart:], rsp[ntpTransmitRspStart:ntpTransmitRspEnd]) {
		log.Debugf("originate timestamp in the response does not match transmit timestamp in the request: 0x%x 0x%x",
			rsp[ntpTransmitRspStart:ntpTransmitRspEnd], req[40:])

		return 0
	}

	version := (rsp[0] >> 3) & 7
	if version != ntpVersion {
		log.Debugf("invalid NTP version in the response: %d", version)
		return 0
	}

	mode := rsp[0] & 7
	if mode != ntpModeServer {
		log.Debugf("invalid mode in the response: %d", mode)
		return 0
	}

	if 15 < rsp[1] {
		log.Debugf("invalid stratum in the response: %d", rsp[1])
		return 0
	}

	var f float64
	for i := 0; i < 8; i++ {
		f = 256*f + float64(rsp[40+i])
	}

	transmit := f / ntpScale
	if transmit == 0 {
		log.Debugf("invalid transmit timestamp in the response: %v", transmit)
		return 0
	}

	return 1
}

func (p *Plugin) udpExpect(service string, address string) (result int) {
	var conn net.Conn
	var err error

	if conn, err = net.DialTimeout("udp", address, time.Second*p.options.Timeout); err != nil {
		log.Debugf("UDP expect network error: cannot connect to [%s]: %s", address, err.Error())
		return
	}
	defer conn.Close()

	if err = conn.SetDeadline(time.Now().Add(time.Second * p.options.Timeout)); err != nil {
		return
	}

	req := make([]byte, ntpPacketSize)
	p.createRequest(req)

	if _, err = conn.Write(req); err != nil {
		log.Debugf("UDP expect network error: cannot write to [%s]: %s", address, err.Error())
		return
	}

	var ln int
	rsp := make([]byte, ntpPacketSize)

	if ln, err = conn.Read(rsp); err != nil {
		log.Debugf("UDP expect network error: cannot read from [%s]: %s", address, err.Error())
		return
	}

	return p.validateResponse(rsp, ln, req)
}

func (p *Plugin) exportNetService(params []string) int {
	var ip, port string
	service := params[0]

	if len(params) > 1 && params[1] != "" {
		ip = params[1]
	} else {
		ip = "127.0.0.1"
	}

	if len(params) == 3 && params[2] != "" {
		port = params[2]
	} else {
		port = service
	}

	return p.udpExpect(service, net.JoinHostPort(ip, port))
}

func toFixed(num float64, precision int) float64 {
	output := math.Pow(10, float64(precision))
	return math.Round(num*output) / output
}

func (p *Plugin) exportNetServicePerf(params []string) float64 {
	const floatPrecision = 0.0001

	start := time.Now()
	ret := p.exportNetService(params)

	if ret == 1 {
		elapsedTime := toFixed(time.Since(start).Seconds(), 6)

		if elapsedTime < floatPrecision {
			elapsedTime = floatPrecision
		}
		return elapsedTime
	}
	return 0.0
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "net.udp.service", "net.udp.service.perf":
		if len(params) > 3 {
			err = errors.New(errorTooManyParams)
			return
		}
		if len(params) < 1 || (len(params) == 1 && len(params[0]) == 0) {
			err = errors.New(errorInvalidFirstParam)
			return
		}
		if params[0] != "ntp" {
			err = errors.New(errorInvalidFirstParam)
			return
		}

		if len(params) == 3 && len(params[2]) != 0 {
			if _, err = strconv.ParseUint(params[2], 10, 16); err != nil {
				err = errors.New(errorInvalidThirdParam)
				return
			}
		}

		if key == "net.udp.service" {
			return p.exportNetService(params), nil
		} else if key == "net.udp.service.perf" {
			return p.exportNetServicePerf(params), nil
		}
	case "net.udp.socket.count":
		return p.exportNetUdpSocketCount(params)
	}

	/* SHOULD_NEVER_HAPPEN */
	return nil, errors.New(errorUnsupportedMetric)
}

func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	if err := conf.Unmarshal(options, &p.options); err != nil {
		p.Warningf("cannot unmarshal configuration options: %s", err)
	}
	if p.options.Timeout == 0 {
		p.options.Timeout = time.Duration(global.Timeout)
	}
}

func (p *Plugin) Validate(options interface{}) error {
	var o Options
	return conf.Unmarshal(options, &o)
}

func init() {
	plugin.RegisterMetrics(&impl, "UDP",
		"net.udp.service", "Checks if service is running and responding to UDP requests.",
		"net.udp.service.perf", "Checks performance of UDP service.",
		"net.udp.socket.count", "Returns number of TCP sockets that match parameters.")
}
