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

package cpucollector


import (
	"errors"
)

func (p *Plugin) collect() (err error) {
	return errors.New("Not implemented")
}

func (p *Plugin) numCPU() int {
	// TODO: implementation
	return 0
}

func (p *Plugin) getStateIndex(state string) (index int, err error) {
	switch state {
	case "", "system":
		index = stateSystem
	default:
		err = errors.New("unsupported state")
	}
	return 0, errors.New("Not implemented")
}
