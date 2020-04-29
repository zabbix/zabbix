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

package tcpudp

import (
	"errors"
	"fmt"
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
	errorTooManyParams      = "Too many parameters."
	errorUnsupportedMetric  = "Unsupported metric."
)

const (
	tcpExpectFail   = -1
	tcpExpectOk     = 0
	tcpExpectIgnore = 1
)

type Options struct {
	Timeout  time.Duration `conf:"optional,range=1:30"`
	Capacity int           `conf:"optional,range=1:100"`
}

// Plugin -
type Plugin struct {
	plugin.Base
	options Options
}

var impl Plugin

func (p *Plugin) exportNetTcpListen(params []string) (result interface{}, err error) {
	if len(params) > 1 {
		return nil, errors.New(errorTooManyParams)
	}
	if len(params) == 0 || params[0] == "" {
		return nil, errors.New(errorInvalidFirstParam)
	}
	port, err := strconv.ParseUint(params[0], 10, 16)
	if err != nil {
		return nil, errors.New(errorInvalidFirstParam)
	}

	return exportSystemTcpListen(uint16(port))
}

func (p *Plugin) exportNetTcpPort(params []string) (result int, err error) {
	if len(params) > 2 {
		err = errors.New(errorTooManyParams)
		return
	}
	if len(params) < 2 || len(params[1]) == 0 {
		err = errors.New(errorInvalidSecondParam)
		return
	}

	port := params[1]

	if _, err = strconv.ParseUint(port, 10, 16); err != nil {
		err = errors.New(errorInvalidSecondParam)
		return
	}

	var address string

	if params[0] == "" {
		address = net.JoinHostPort("127.0.0.1", port)
	} else {
		address = net.JoinHostPort(params[0], port)
	}

	if _, err := net.Dial("tcp", address); err != nil {
		return 0, nil
	}
	return 1, nil
}

func (p *Plugin) validateSsh(buf []byte, conn net.Conn) int {
	var major, minor int
	var sendBuf string
	ret := tcpExpectFail

	if _, err := fmt.Sscanf(string(buf), "SSH-%d.%d", &major, &minor); err == nil {
		sendBuf = fmt.Sprintf("SSH-%d.%d-zabbix_agent\r\n", major, minor)
		ret = tcpExpectOk
	}

	if ret == tcpExpectFail {
		sendBuf = fmt.Sprintf("0\n")
	}

	if _, err := conn.Write([]byte(sendBuf)); err != nil {
		log.Debugf("SSH check error: %s\n", err.Error())
	}

	return ret
}

func (p *Plugin) validateSmtp(buf []byte) int {
	if string(buf[:3]) == "220" {
		if string(buf[3]) == "-" {
			return tcpExpectIgnore
		}
		if string(buf[3]) == "" || string(buf[3]) == " " {
			return tcpExpectOk
		}
	}
	return tcpExpectFail
}

func (p *Plugin) validateFtp(buf []byte) int {
	if string(buf[:4]) == "220 " {
		return tcpExpectOk
	}
	return tcpExpectIgnore
}

func (p *Plugin) validatePop(buf []byte) int {
	if string(buf[:3]) == "+OK" {
		return tcpExpectOk
	}
	return tcpExpectFail
}

func (p *Plugin) validateNntp(buf []byte) int {
	if string(buf[:3]) == "200" || string(buf[:3]) == "201" {
		return tcpExpectOk
	}
	return tcpExpectFail
}

func (p *Plugin) validateImap(buf []byte) int {
	if string(buf[:4]) == "* OK" {
		return tcpExpectOk
	}
	return tcpExpectFail
}

func (p *Plugin) tcpExpect(service string, address string) (result int) {
	var conn net.Conn
	var err error

	if conn, err = net.DialTimeout("tcp", address, time.Second*p.options.Timeout); err != nil {
		log.Debugf("TCP expect network error: cannot connect to [%s]: %s", address, err.Error())
		return
	}
	defer conn.Close()

	if service == "http" || service == "tcp" {
		return 1
	}

	if err = conn.SetReadDeadline(time.Now().Add(time.Second * p.options.Timeout)); err != nil {
		return
	}

	var sendToClose string
	var checkResult int
	buf := make([]byte, 2048)

	for {
		if _, err = conn.Read(buf); err == nil {
			switch service {
			case "ssh":
				checkResult = p.validateSsh(buf, conn)
			case "smtp":
				checkResult = p.validateSmtp(buf)
				sendToClose = fmt.Sprintf("%s", "QUIT\r\n")
			case "ftp":
				checkResult = p.validateFtp(buf)
				sendToClose = fmt.Sprintf("%s", "QUIT\r\n")
			case "pop":
				checkResult = p.validatePop(buf)
				sendToClose = fmt.Sprintf("%s", "QUIT\r\n")
			case "nntp":
				checkResult = p.validateNntp(buf)
				sendToClose = fmt.Sprintf("%s", "QUIT\r\n")
			case "imap":
				checkResult = p.validateImap(buf)
				sendToClose = fmt.Sprintf("%s", "a1 LOGOUT\r\n")
			}

			if checkResult == tcpExpectOk {
				break
			}
		} else {
			log.Debugf("TCP expect network error: cannot read from [%s]: %s", address, err.Error())
			return 0
		}
	}

	if checkResult == tcpExpectOk {
		if sendToClose != "" {
			conn.Write([]byte(sendToClose))
		}
		result = 1
	}

	if checkResult == tcpExpectFail {
		log.Debugf("TCP expect content error, received [%s]", buf)
	}

	return
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
		if service != "pop" {
			port = service
		} else {
			port = "pop3"
		}
	}
	return p.tcpExpect(service, net.JoinHostPort(ip, port))
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
	case "net.tcp.listen":
		return p.exportNetTcpListen(params)
	case "net.tcp.port":
		return p.exportNetTcpPort(params)
	case "net.tcp.service", "net.tcp.service.perf":
		if len(params) > 3 {
			err = errors.New(errorTooManyParams)
			return
		}
		if len(params) < 1 || (len(params) == 1 && len(params[0]) == 0) {
			err = errors.New(errorInvalidFirstParam)
			return
		}

		switch params[0] {
		case "tcp":
			if len(params) != 3 || len(params[2]) == 0 {
				err = errors.New(errorInvalidThirdParam)
				return
			}
		case "ssh", "smtp", "ftp", "pop", "nntp", "imap", "http":
		default:
			err = errors.New(errorInvalidFirstParam)
			return
		}

		if len(params) == 3 && len(params[2]) != 0 {
			if _, err = strconv.ParseUint(params[2], 10, 16); err != nil {
				err = errors.New(errorInvalidThirdParam)
				return
			}
		}

		if key == "net.tcp.service" {
			return p.exportNetService(params), nil
		} else if key == "net.tcp.service.perf" {
			return p.exportNetServicePerf(params), nil
		}
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
