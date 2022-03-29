//go:build windows
// +build windows

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
	"golang.org/x/sys/windows"
	"zabbix.com/pkg/zbxerr"
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
		if params[1] != "" && params[1] != ownertype {
			return nil, fmt.Errorf("Invalid second parameter: %s.", params[1])
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

	sd, err := windows.GetNamedSecurityInfo(path, windows.SE_FILE_OBJECT, windows.OWNER_SECURITY_INFORMATION)
	if err != nil {
		return nil, zbxerr.New(fmt.Sprintf("Cannot obtain %s information", path)).Wrap(err)
	}
	if !sd.IsValid() {
		return nil, fmt.Errorf("Cannot obtain %s information: Invalid security descriptor.", path)
	}

	sdOwner, _, err := sd.Owner()
	if err != nil {
		return nil, zbxerr.New(fmt.Sprintf("Cannot obtain %s owner information", path)).Wrap(err)
	}
	if !sdOwner.IsValid() {
		return nil, fmt.Errorf("Cannot obtain %s information: Invalid security descriptor owner.", path)
	}

	var ret string

	switch ownertype + resulttype {
	case "userid":
		ret = sdOwner.String()
	case "username":
		account, domain, _, err := sdOwner.LookupAccount("")
		if err != nil {
			return nil, zbxerr.New(fmt.Sprintf("Cannot obtain %s owner name information", path)).Wrap(err)
		}

		if ret = domain; ret != "" {
			ret += "\\"
		}
		ret += account
	}

	return ret, nil
}
