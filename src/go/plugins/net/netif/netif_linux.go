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

package netif

import (
	"bufio"
	"encoding/json"
	"errors"
	"fmt"
	"strconv"
	"strings"

	"git.zabbix.com/ap/plugin-support/plugin"
	"git.zabbix.com/ap/plugin-support/std"
)

const (
	errorCannotFindIf     = "Cannot find information for this network interface in /proc/net/dev."
	errorCannotOpenNetDev = "Cannot open /proc/net/dev: %s"
)

var stdOs std.Os

var mapNetStatIn = map[string]uint{
	"bytes":      0,
	"packets":    1,
	"errors":     2,
	"dropped":    3,
	"overruns":   4,
	"frame":      5,
	"compressed": 6,
	"multicast":  7,
}

var mapNetStatOut = map[string]uint{
	"bytes":      8,
	"packets":    9,
	"errors":     10,
	"dropped":    11,
	"overruns":   12,
	"collisions": 13,
	"carrier":    14,
	"compressed": 15,
}

func (p *Plugin) addStatNum(statName string, mapNetStat map[string]uint, statNums *[]uint) error {
	if statNum, ok := mapNetStat[statName]; ok {
		*statNums = append(*statNums, statNum)
	} else {
		return errors.New(errorInvalidSecondParam)
	}
	return nil
}

func (p *Plugin) getNetStats(networkIf string, statName string, dir dirFlag) (result uint64, err error) {
	var statNums []uint

	if dir&dirIn != 0 {
		if err = p.addStatNum(statName, mapNetStatIn, &statNums); err != nil {
			return
		}
	}

	if dir&dirOut != 0 {
		if err = p.addStatNum(statName, mapNetStatOut, &statNums); err != nil {
			return
		}
	}

	file, err := stdOs.Open("/proc/net/dev")
	if err != nil {
		return 0, fmt.Errorf(errorCannotOpenNetDev, err)
	}
	defer file.Close()

	var total uint64
loop:
	for sLines := bufio.NewScanner(file); sLines.Scan(); {
		dev := strings.Split(sLines.Text(), ":")

		if len(dev) > 1 && networkIf == strings.TrimSpace(dev[0]) {
			stats := strings.Fields(dev[1])

			if len(stats) >= 16 {
				for _, statNum := range statNums {
					var res uint64

					if res, err = strconv.ParseUint(stats[statNum], 10, 64); err != nil {
						break loop
					}
					total += res
				}
				return total, nil
			}
			break
		}
	}
	err = errors.New(errorCannotFindIf)
	return
}

func (p *Plugin) getDevDiscovery() (netInterfaces []msgIfDiscovery, err error) {
	var f std.File
	if f, err = stdOs.Open("/proc/net/dev"); err != nil {
		return nil, fmt.Errorf(errorCannotOpenNetDev, err)
	}
	defer f.Close()

	netInterfaces = make([]msgIfDiscovery, 0)
	for sLines := bufio.NewScanner(f); sLines.Scan(); {
		dev := strings.Split(sLines.Text(), ":")
		if len(dev) > 1 {
			netInterfaces = append(netInterfaces, msgIfDiscovery{strings.TrimSpace(dev[0]), nil})
		}
	}

	return netInterfaces, nil
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	var direction dirFlag
	var mode string

	switch key {
	case "net.if.discovery":
		if len(params) > 0 {
			return nil, errors.New(errorParametersNotAllowed)
		}
		var devices []msgIfDiscovery
		if devices, err = p.getDevDiscovery(); err != nil {
			return
		}
		var b []byte
		if b, err = json.Marshal(devices); err != nil {
			return
		}
		return string(b), nil
	case "net.if.collisions":
		if len(params) > 1 {
			return nil, errors.New(errorTooManyParams)
		}

		if len(params) < 1 || params[0] == "" {
			return nil, errors.New(errorEmptyIfName)
		}
		return p.getNetStats(params[0], "collisions", dirOut)
	case "net.if.in":
		direction = dirIn
	case "net.if.out":
		direction = dirOut
	case "net.if.total":
		direction = dirIn | dirOut
	default:
		/* SHOULD_NEVER_HAPPEN */
		return nil, errors.New(errorUnsupportedMetric)
	}

	if len(params) < 1 || params[0] == "" {
		return nil, errors.New(errorEmptyIfName)
	}

	if len(params) > 2 {
		return nil, errors.New(errorTooManyParams)
	}

	if len(params) == 2 && params[1] != "" {
		mode = params[1]
	} else {
		mode = "bytes"
	}

	return p.getNetStats(params[0], mode, direction)
}

func init() {
	stdOs = std.NewOs()

	plugin.RegisterMetrics(&impl, "NetIf",
		"net.if.collisions", "Returns number of out-of-window collisions.",
		"net.if.in", "Returns incoming traffic statistics on network interface.",
		"net.if.out", "Returns outgoing traffic statistics on network interface.",
		"net.if.total", "Returns sum of incoming and outgoing traffic statistics on network interface.",
		"net.if.discovery", "Returns list of network interfaces. Used for low-level discovery.")

}
