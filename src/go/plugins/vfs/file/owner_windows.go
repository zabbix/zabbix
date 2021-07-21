// +build windows

/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
)

// Export -
func (p *Plugin) exportOwner(params []string) (result interface{}, err error) {
	if len(params) > 3 {
		return nil, errors.New("Too many parameters.")
	}
	if len(params) == 0 || params[0] == "" {
		return nil, errors.New("Invalid first parameter.")
	}
	ownertype := "user"
	if len(params) > 1 && params[1] != "" && params[1] != "user" {
		return nil, fmt.Errorf("Invalid second parameter: %s", params[1])
	}
	resulttype := "name"
	if len(params) > 2 && params[2] != "" {
		if params[2] != "name" && params[2] != "SID" {
			return nil, fmt.Errorf("Invalid second parameter: %s", params[2])
		}
		resulttype = params[2]
	}

	sd, err := windows.GetNamedSecurityInfo(params[0], windows.SE_FILE_OBJECT, windows.OWNER_SECURITY_INFORMATION)
	if err != nil {
		return nil, fmt.Errorf("Cannot obtain %s information: %s", params[0], err)
	}
	if !sd.IsValid() {
		return nil, fmt.Errorf("Cannot obtain %s information: Invalid security descriptor", params[0])
	}
	sdOwner, _, err := sd.Owner()
	if err != nil {
		return nil, fmt.Errorf("Cannot obtain %s owner information: %s", params[0], err)
	}
	if !sdOwner.IsValid() {
		return nil, fmt.Errorf("Cannot obtain %s information: Invalid security descriptor owner", params[0])
	}

	var ret string
	switch ownertype + resulttype {
	case "userSID":
		ret = sdOwner.String()
	case "username":
		account, domain, _, err := sdOwner.LookupAccount("")
		if err != nil {
			return nil, fmt.Errorf("Cannot obtain %s owner name information: %s", params[0], err)
		}
		ret := domain
		if ret != "" {
			ret += "\\"
		}
		ret += account
	}

	return ret, nil
}
