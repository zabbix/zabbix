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

package hostname

import (
	"fmt"
	"syscall"
)

func arrayToString(hostnameArray *[65]int8) string {
	var byteString [65]byte
	var indexLength int
	for ; indexLength < len(hostnameArray); indexLength++ {
		if 0 == hostnameArray[indexLength] {
			break
		}
		byteString[indexLength] = uint8(hostnameArray[indexLength])
	}
	return string(byteString[:indexLength])
}

func getHostname() (hostname string, err error) {
	var utsname syscall.Utsname
	if err = syscall.Uname(&utsname); err != nil {
		err = fmt.Errorf("Cannot obtain system information: %s", err.Error())
		return
	}
	hostname = fmt.Sprintf("%s", uname.arrayToString(&utsname.Nodename))

	return hostname, nil
}
