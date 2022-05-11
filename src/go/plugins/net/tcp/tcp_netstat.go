//go:build linux || (windows && amd64)
// +build linux windows,amd64

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

package tcpudp

import (
	"errors"
	"net"
	"strconv"

	"github.com/cakturk/go-netstat/netstat"
)

// exportNetTcpSocketCount - returns number of TCP sockets that match parameters.
func (p *Plugin) exportNetTcpSocketCount(params []string) (result int, err error) {
	if len(params) > 5 {
		return 0, errors.New(errorTooManyParams)
	}

	var laddres net.IP
	var lNet *net.IPNet

	if len(params) > 0 && len(params[0]) > 0 {
		if ip := net.ParseIP(params[0]); ip != nil {
			laddres = ip
		} else if _, lNet, err = net.ParseCIDR(params[0]); err != nil {
			return 0, errors.New(errorInvalidFirstParam)
		}
	}

	lport := 0
	if len(params) > 1 && len(params[1]) > 0 {
		if port, err := strconv.ParseUint(params[1], 10, 16); err != nil {
			if lport, err = net.LookupPort("tcp", params[1]); err != nil {
				if lport, err = net.LookupPort("tcp6", params[1]); err != nil {
					return 0, errors.New(errorInvalidSecondParam)
				}
			}
		} else {
			lport = int(port)
		}
	}

	var raddres net.IP
	var rNet *net.IPNet

	if len(params) > 2 && len(params[2]) > 0 {
		if ip := net.ParseIP(params[2]); ip != nil {
			raddres = ip
		} else if _, rNet, err = net.ParseCIDR(params[2]); err != nil {
			return 0, errors.New(errorInvalidThirdParam)
		}
	}

	rport := 0
	if len(params) > 3 && len(params[3]) > 0 {
		if port, err := strconv.ParseUint(params[3], 10, 16); err != nil {
			if rport, err = net.LookupPort("tcp", params[3]); err != nil {
				if rport, err = net.LookupPort("tcp6", params[3]); err != nil {
					return 0, errors.New(errorInvalidFourthParam)
				}
			}
		} else {
			rport = int(port)
		}
	}

	var state netstat.SkState

	if len(params) > 4 && len(params[4]) > 0 {
		switch params[4] {
		case "established":
			state = netstat.Established
		case "syn_sent":
			state = netstat.SynSent
		case "syn_recv":
			state = netstat.SynRecv
		case "fin_wait1":
			state = netstat.FinWait1
		case "fin_wait2":
			state = netstat.FinWait2
		case "time_wait":
			state = netstat.TimeWait
		case "close":
			state = netstat.Close
		case "close_wait":
			state = netstat.CloseWait
		case "last_ack":
			state = netstat.LastAck
		case "listen":
			state = netstat.Listen
		case "closing":
			state = netstat.Closing
		default:
			return 0, errors.New(errorInvalidFifthParam)
		}
	}

	return netStatTcpCount(laddres, lNet, lport, raddres, rNet, rport, state)
}

// netStatTcpCount - returns number of TCP sockets that match parameters.
func netStatTcpCount(laddres net.IP, lNet *net.IPNet, lport int, raddres net.IP, rNet *net.IPNet, rport int,
	state netstat.SkState) (result int, err error) {

	tabs, err := netstat.TCPSocks(func(s *netstat.SockTabEntry) bool {
		if state != 0 && s.State != state {
			return false
		}
		if lport != 0 && s.LocalAddr.Port != uint16(lport) {
			return false
		}
		if laddres != nil && !s.LocalAddr.IP.Equal(laddres) {
			return false
		}
		if lNet != nil && !lNet.Contains(s.LocalAddr.IP) {
			return false
		}
		if rport != 0 && s.RemoteAddr.Port != uint16(rport) {
			return false
		}
		if raddres != nil && !s.RemoteAddr.IP.Equal(raddres) {
			return false
		}
		if rNet != nil && !rNet.Contains(s.RemoteAddr.IP) {
			return false
		}

		return true
	})
	if err != nil {
		return 0, err
	}

	tabs6, err := netstat.TCP6Socks(func(s *netstat.SockTabEntry) bool {
		if state != 0 && s.State != state {
			return false
		}
		if lport != 0 && s.LocalAddr.Port != uint16(lport) {
			return false
		}
		if laddres != nil && !s.LocalAddr.IP.Equal(laddres) {
			return false
		}
		if lNet != nil && !lNet.Contains(s.LocalAddr.IP) {
			return false
		}
		if rport != 0 && s.RemoteAddr.Port != uint16(rport) {
			return false
		}
		if raddres != nil && !s.RemoteAddr.IP.Equal(raddres) {
			return false
		}
		if rNet != nil && !rNet.Contains(s.RemoteAddr.IP) {
			return false
		}

		return true
	})
	if err != nil {
		return 0, err
	}

	return len(tabs) + len(tabs6), nil
}
