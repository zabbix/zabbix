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

package uname

import (
	"errors"
	"fmt"
	"strings"
	"syscall"
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
	uname = fmt.Sprintf("%s %s %s %s %s", arrayToString(&utsname.Sysname), arrayToString(&utsname.Nodename),
		arrayToString(&utsname.Release), arrayToString(&utsname.Version), arrayToString(&utsname.Machine))

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
		hostname = arrayToString(&utsname.Nodename)
	case "shorthost":
		hostname = arrayToString(&utsname.Nodename)
		if idx := strings.Index(hostname, "."); idx > 0 {
			hostname = hostname[:idx]
		}
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

	return arrayToString(&utsname.Machine), nil
}
