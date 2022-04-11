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

	"zabbix.com/pkg/zbxerr"
)

// Export -
func (p *Plugin) exportTime(params []string) (result interface{}, err error) {
	if len(params) > 2 || len(params) == 0 {
		return nil, errors.New("Invalid number of parameters.")
	}
	if "" == params[0] {
		return nil, errors.New("Invalid first parameter.")
	}
	if f, err := stdOs.Stat(params[0]); err != nil {
		return nil, zbxerr.New(fmt.Sprintf("Cannot obtain file information")).Wrap(err)
	} else {
		if len(params) == 1 || params[1] == "" || params[1] == "modify" {
			return f.ModTime().Unix(), nil
		} else if params[1] == "access" {
			return f.Sys().(*syscall.Stat_t).Atim.Sec, nil
		} else if params[1] == "change" {
			return f.Sys().(*syscall.Stat_t).Ctim.Sec, nil
		} else {
			return nil, errors.New("Invalid second parameter.")
		}
	}
}
