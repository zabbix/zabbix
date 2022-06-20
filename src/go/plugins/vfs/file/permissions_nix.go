//go:build !windows
// +build !windows

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

package file

import (
	"errors"
	"fmt"
	"syscall"

	"git.zabbix.com/ap/plugin-support/zbxerr"
)

// mode2str - permission printable format
func mode2str(mode uint32) string {
	return fmt.Sprintf("%04o", mode&07777)
}

// exportPermissions - returns 4-digit string containing octal number with Unix permissions
func (p *Plugin) exportPermissions(params []string) (result interface{}, err error) {
	if len(params) > 1 {
		return nil, zbxerr.ErrorTooManyParameters
	}
	if len(params) == 0 || len(params[0]) == 0 {
		return nil, errors.New("Invalid first parameter.")
	}

	info, err := stdOs.Stat(params[0])
	if err != nil {
		return nil, err
	}

	stat := info.Sys().(*syscall.Stat_t)
	if stat == nil {
		return nil, fmt.Errorf("Cannot obtain %s permission information.", params[0])
	}

	return mode2str(stat.Mode), nil
}
