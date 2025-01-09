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

package swap

import (
	"errors"
	"fmt"

	"golang.zabbix.com/sdk/plugin"
)

var impl Plugin

// Plugin -
type Plugin struct {
	plugin.Base
}

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "system.swap.size":
		if len(params) > 2 {
			return nil, errors.New("Too many parameters.")
		}

		if len(params) > 0 && params[0] != "" && params[0] != "all" {
			return nil, errors.New("Invalid first parameter.")
		}

		total, avail, err := getSwapSize()
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
	case "system.swap.in", "system.swap.out":
		if len(params) > 2 {
			return nil, errors.New("Too many parameters.")
		}

		var swapdev string
		if len(params) > 0 {
			swapdev = params[0]
		}

		var io, sect, pag uint64

		if key == "system.swap.in" {
			io, sect, pag, err = getSwapStatsIn(swapdev)
		} else {
			io, sect, pag, err = getSwapStatsOut(swapdev)
		}

		if err != nil {
			return nil, err
		}

		var mode string
		if len(params) > 1 {
			mode = params[1]
		}

		if len(mode) == 0 || mode == "pages" {
			if len(swapdev) > 0 && swapdev != "all" {
				return nil, errors.New("Invalid second parameter.")
			}

			return pag, nil
		} else if mode == "sectors" {
			return sect, nil
		} else if mode == "count" {
			return io, nil
		}

		return nil, errors.New("Invalid second parameter.")
	default:
		return nil, plugin.UnsupportedMetricError
	}
}
