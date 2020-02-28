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

package uname

import (
	"fmt"
	"syscall"
)

func getUname() (uname string, err error) {
	var utsname syscall.Utsname
	if err = syscall.Uname(&utsname); err != nil {
		err = fmt.Errorf("Cannot obtain system information: %s", err.Error())
		return
	}
	uname = fmt.Sprintf("%s %s %s %s %s", arrayToString(&utsname.Sysname), arrayToString(&utsname.Nodename),
		arrayToString(&utsname.Release), arrayToString(&utsname.Version), arrayToString(&utsname.Machine))

	return uname, nil
}

func getHostname() (hostname string, err error) {
	var utsname syscall.Utsname
	if err = syscall.Uname(&utsname); err != nil {
		err = fmt.Errorf("Cannot obtain system information: %s", err.Error())
		return
	}

	return arrayToString(&utsname.Nodename), nil
}

func getSwArch() (uname string, err error) {
	var utsname syscall.Utsname
	if err = syscall.Uname(&utsname); err != nil {
		err = fmt.Errorf("Cannot obtain system information: %s", err.Error())
		return
	}

	return arrayToString(&utsname.Machine), nil
}
