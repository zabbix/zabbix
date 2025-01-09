//go:build !windows
// +build !windows

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

package file

import (
	"errors"
	"fmt"
	"syscall"

	"golang.zabbix.com/sdk/zbxerr"
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
