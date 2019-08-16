/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package filestats

import (
	"errors"
	"fmt"
	"syscall"
	"zabbix/internal/plugin"
	"zabbix/pkg/std"
)

// Plugin -
type Plugin struct {
	plugin.Base
}

var impl Plugin

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "vfs.file.time":
		if len(params) > 2 || len(params) == 0 {
			return nil, errors.New("Wrong number of parameters")
		}
		if "" == params[0] {
			return nil, errors.New("Invalid first parameter")
		}
		if f, err := stdOs.Stat(params[0]); err != nil {
			return nil, fmt.Errorf("Cannot obtain file %s information: %s", params[0], err)
		} else {
			if len(params) == 1 || params[1] == "" || params[1] == "modify" {
				return f.ModTime().Unix(), nil
			} else if params[1] == "access" {
				return f.Sys().(*syscall.Stat_t).Atim.Sec, nil
			} else if params[1] == "change" {
				return f.Sys().(*syscall.Stat_t).Ctim.Sec, nil
			} else {
				return nil, errors.New("Invalid second parameter")
			}
		}

	case "vfs.file.size":
		if len(params) != 1 {
			return nil, errors.New("Wrong number of parameters")
		}
		if "" == params[0] {
			return nil, errors.New("Invalid first parameter")
		}

		if f, err := stdOs.Stat(params[0]); err == nil {
			return f.Size(), nil
		} else {
			return nil, fmt.Errorf("Cannot obtain file %s information: %s", params[0], err)
		}

	case "vfs.file.exists":

		if len(params) != 1 {
			return nil, errors.New("Wrong number of parameters")
		}
		if "" == params[0] {
			return nil, errors.New("Invalid first parameter")
		}
		ret := 0

		if f, err := stdOs.Stat(params[0]); err == nil {
			if mode := f.Mode(); mode.IsRegular() {
				ret = 1
			}
		} else if stdOs.IsExist(err) {
			ret = 1
		}
		return ret, nil

	default:
		return nil, errors.New("Unsupported metric")
	}

}

var stdOs std.Os

func init() {
	plugin.RegisterMetric(&impl, "filestats", "vfs.file.exists", "Returns if file exists or not")
	plugin.RegisterMetric(&impl, "filestats", "vfs.file.size", "Returns file size")
	plugin.RegisterMetric(&impl, "filestats", "vfs.file.time", "Returns file time information")
	stdOs = std.NewOs()
}
