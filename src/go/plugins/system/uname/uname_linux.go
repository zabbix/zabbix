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

package uname

import (
	"errors"
	"fmt"
	"net"
	"strings"
	"syscall"

	"golang.zabbix.com/agent2/util"
)

func getUname(params []string) (uname string, err error) {
	if len(params) > 0 {
		return "", errors.New("Too many parameters.")
	}

	var utsname syscall.Utsname
	if err = syscall.Uname(&utsname); err != nil {
		err = fmt.Errorf("Cannot obtain system information: %s", err.Error())
		return
	}
	uname = fmt.Sprintf("%s %s %s %s %s", util.UnameArrayToString(&utsname.Sysname),
		util.UnameArrayToString(&utsname.Nodename), util.UnameArrayToString(&utsname.Release),
		util.UnameArrayToString(&utsname.Version), util.UnameArrayToString(&utsname.Machine))

	return uname, nil
}

func getHostname(params []string) (hostname string, err error) {
	if len(params) > 2 {
		return "", errors.New("Too many parameters.")
	}

	var mode, transform string

	if len(params) > 0 {
		mode = params[0]
		if len(params) > 1 {
			transform = params[1]
		}
	}

	var utsname syscall.Utsname
	if err = syscall.Uname(&utsname); err != nil {
		err = fmt.Errorf("Cannot obtain system information: %s", err.Error())
		return
	}

	switch mode {
	case "host", "":
		hostname = util.UnameArrayToString(&utsname.Nodename)
	case "shorthost":
		hostname = util.UnameArrayToString(&utsname.Nodename)
		if idx := strings.Index(hostname, "."); idx > 0 {
			hostname = hostname[:idx]
		}
	case "fqdn":
		var tmp string
		hostname = util.UnameArrayToString(&utsname.Nodename)

		tmp, err = net.LookupCNAME(hostname)
		if err == nil {
			hostname = tmp
		}

		hostname = strings.Trim(hostname, " .\n\r")
	case "netbios":
		return "", errors.New("NetBIOS is not supported on the current platform.")
	default:
		return "", errors.New("Invalid first parameter.")
	}

	switch transform {
	case "lower":
		hostname = strings.ToLower(hostname)
	case "none", "":
		break
	default:
		return "", errors.New("Invalid second parameter.")
	}

	return
}

func getSwArch(params []string) (uname string, err error) {
	if len(params) > 0 {
		return "", errors.New("Too many parameters.")
	}

	var utsname syscall.Utsname
	if err = syscall.Uname(&utsname); err != nil {
		err = fmt.Errorf("Cannot obtain system information: %s", err.Error())
		return
	}

	return util.UnameArrayToString(&utsname.Machine), nil
}
