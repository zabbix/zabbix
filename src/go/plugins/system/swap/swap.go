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

package swap

import (
	"errors"
	"fmt"

	"zabbix.com/pkg/plugin"
)

// Plugin -
type Plugin struct {
	plugin.Base
}

var impl Plugin

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if key != "system.swap.size" {
		return nil, plugin.UnsupportedMetricError
	}

	if len(params) > 2 {
		return nil, errors.New("Too many parameters.")
	}

	if len(params) > 0 && params[0] != "" && params[0] != "all" {
		return nil, errors.New("Invalid first parameter.")
	}

	total, avail, err := getSwap()
	if err != nil {
		return nil, fmt.Errorf("Failed to get swap data: %s", err.Error())
	}

	if avail > total {
		avail = total
	}

	var mode string
	if len(params) == 2 && params[1] != "" {
		mode = params[1]
	}

	switch mode {
	case "total":
		return total, nil
	case "", "free":
		return avail, nil
	case "used":
		return total - avail, nil
	case "pfree":
		if total == 0 {
			return 100.0, nil
		}
		return float64(avail) / float64(total) * 100, nil
	case "pused":
		if total == 0 {
			return 0.0, nil
		}
		return float64(total-avail) / float64(total) * 100, nil
	default:
		return nil, errors.New("Invalid second parameter.")
	}
}

func init() {
	plugin.RegisterMetrics(&impl, "Swap",
		"system.swap.size", "Returns Swap space size in bytes or in percentage from total.",
	)
}
