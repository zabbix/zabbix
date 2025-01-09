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
	"os"
	"os/user"
	"strconv"
	"syscall"

	"golang.zabbix.com/sdk/zbxerr"
)

// Export -
func (p *Plugin) exportOwner(params []string) (result interface{}, err error) {
	var path string
	resulttype := "name"
	ownertype := "user"

	switch len(params) {
	case 3:
		if params[2] != "" {
			if params[2] != "name" && params[2] != "id" {
				return nil, fmt.Errorf("Invalid third parameter: %s.", params[2])
			}

			resulttype = params[2]
		}

		fallthrough
	case 2:
		if params[1] != "" {
			if params[1] != "user" && params[1] != "group" {
				return nil, fmt.Errorf("Invalid second parameter: %s.", params[1])
			}

			ownertype = params[1]
		}

		fallthrough
	case 1:
		if path = params[0]; path == "" {
			return nil, errors.New("Invalid first parameter.")
		}
	case 0:
		return nil, zbxerr.ErrorTooFewParameters
	default:
		return nil, zbxerr.ErrorTooManyParameters
	}

	info, err := os.Lstat(path)
	if err != nil {
		return nil, err
	}

	stat := info.Sys().(*syscall.Stat_t)
	if stat == nil {
		return nil, fmt.Errorf("Cannot obtain %s owner information.", path)
	}

	var ret string

	switch ownertype + resulttype {
	case "userid":
		ret = strconv.FormatUint(uint64(stat.Uid), 10)
	case "groupid":
		ret = strconv.FormatUint(uint64(stat.Gid), 10)
	case "username":
		u := strconv.FormatUint(uint64(stat.Uid), 10)

		if usr, er := user.LookupId(u); er == nil {
			ret = usr.Username
		} else {
			ret = u
		}
	case "groupname":
		g := strconv.FormatUint(uint64(stat.Gid), 10)

		if group, er := user.LookupGroupId(g); er == nil {
			ret = group.Name
		} else {
			ret = g
		}
	}

	return ret, nil
}
