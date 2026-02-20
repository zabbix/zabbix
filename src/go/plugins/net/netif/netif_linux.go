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

package netif

import (
	"encoding/json"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

const (
	errorCannotFindIf = "Cannot find information for this network interface in /proc/net/dev."
	netDevFilepath    = "/proc/net/dev"
)

func init() { //nolint:gochecknoinits // legacy implementation
	impl := &Plugin{}

	err := plugin.RegisterMetrics(
		impl, "NetIf",
		"net.if.collisions", "Returns number of out-of-window collisions.",
		"net.if.in", "Returns incoming traffic statistics on network interface.",
		"net.if.out", "Returns outgoing traffic statistics on network interface.",
		"net.if.total", "Returns sum of incoming and outgoing traffic statistics on network interface.",
		"net.if.discovery", "Returns list of network interfaces. Used for low-level discovery.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}

	impl.netDevFilepath = netDevFilepath
}

// Export implements plugin.Exporter interface.
//
//nolint:cyclop // export function delegates its requests, so high cyclo is expected.
func (p *Plugin) Export(key string, params []string, _ plugin.ContextProvider) (any, error) {
	switch key {
	case "net.if.discovery":
		if len(params) > 0 {
			return nil, errs.New(errorParametersNotAllowed)
		}

		devices, err := p.getDevDiscovery()
		if err != nil {
			return nil, err
		}

		b, err := json.Marshal(devices)
		if err != nil {
			return nil, errs.Wrap(err, "failed to marshal devices")
		}

		return string(b), nil
	case "net.if.collisions":
		err := validateParams(params, 1, 1)
		if err != nil {
			return nil, err
		}

		return p.getNetStats(params[0], "collisions", directionOut)

	case "net.if.in":
		return p.handleNetIfMetric(params, directionIn)

	case "net.if.out":
		return p.handleNetIfMetric(params, directionOut)

	case "net.if.total":
		return p.handleNetIfMetric(params, directionTotal)
	default:
		/* SHOULD_NEVER_HAPPEN */
		return nil, errs.New(errorUnsupportedMetric)
	}
}

func (p *Plugin) handleNetIfMetric(
	params []string, direction networkDirection,
) (any, error) {
	err := validateParams(params, 1, 2)
	if err != nil {
		return nil, err
	}

	mode := "bytes"
	if len(params) == 2 && params[1] != "" {
		mode = params[1]
	}

	return p.getNetStats(params[0], mode, direction)
}

func validateParams(params []string, minParams, maxParams int) error {
	if len(params) < minParams {
		return errs.New(errorEmptyIfName)
	}

	if len(params) > maxParams {
		return errs.New(errorTooManyParams)
	}

	if params[0] == "" {
		return errs.New(errorEmptyIfName)
	}

	return nil
}
