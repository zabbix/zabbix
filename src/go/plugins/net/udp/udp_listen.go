/*
** Copyright (C) 2001-2026 Zabbix SIA
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

package udp

import (
	"fmt"
	"strconv"

	"golang.zabbix.com/agent2/pkg/procfs"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin/comms"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	// using clever pattern that allows fast and easy find open ports.
	//               port in hex    localhost  udp connection status TCP_CLOSE indicating that udp has no state at all.
	udpListenIPv4Pattern = "%04X 00000000:0000 07"
	udpListenIPv6Pattern = "%04X 00000000000000000000000000000000:0000 07"
)

func (p *Plugin) exportNetUDPListen(params []string) (string, error) {
	if len(params) == 0 {
		return "", zbxerr.ErrorTooFewParameters
	}

	if len(params) > 1 {
		return "", zbxerr.ErrorTooManyParameters
	}

	portString := params[0]

	port, err := strconv.ParseUint(portString, 10, 16)
	if err != nil {
		return "", errs.New("invalid port number: " + portString)
	}

	// Parsing IPv4
	ipv4SearchString := fmt.Sprintf(udpListenIPv4Pattern, port)

	parser := procfs.NewParser().
		SetScanStrategy(procfs.StrategyOSReadFile).
		SetMatchMode(procfs.ModeContains).
		SetPattern(ipv4SearchString).
		SetMaxMatches(1)

	data, err := parser.Parse(p.udpListen4Path)
	if err != nil {
		return "", errs.Wrapf(err, "failed to parse %s", p.udpListen4Path)
	}

	if len(data) == 1 {
		return strconv.Itoa(int(comms.PingOk)), nil
	}

	// Parsing IPv6
	ipv6SearchString := fmt.Sprintf(udpListenIPv6Pattern, port)

	parser.SetPattern(ipv6SearchString)

	data, err = parser.Parse(p.udp6ListenPath)
	if err != nil {
		return "", errs.Wrapf(err, "failed to parse %s", p.udp6ListenPath)
	}

	if len(data) == 1 {
		return strconv.Itoa(int(comms.PingOk)), nil
	}

	return strconv.Itoa(int(comms.PingFailed)), nil
}
