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
	"os"
	"runtime"
	"strings"
	"syscall"

	"golang.org/x/sys/windows"
	"zabbix.com/pkg/wmi"
)

func getHostname(params []string) (uname string, err error) {
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

	switch mode {
	case "netbios", "":
		w := make([]uint16, windows.MAX_COMPUTERNAME_LENGTH+1)
		sz := uint32(len(w))
		if err = syscall.GetComputerName(&w[0], &sz); err != nil {
			return "", err
		}
		uname = windows.UTF16ToString(w)
	case "host":
		if uname, err = os.Hostname(); err != nil {
			return "", err
		}
	case "shorthost":
		if uname, err = os.Hostname(); err != nil {
			return "", err
		}
		if idx := strings.Index(uname, "."); idx > 0 {
			uname = uname[:idx]
		}
	default:
		return "", errors.New("Invalid first parameter.")
	}

	switch transform {
	case "lower":
		uname = strings.ToLower(uname)
	case "none", "":
		break
	default:
		return "", errors.New("Invalid second parameter.")
	}

	return
}

func getArch() string {
	switch runtime.GOARCH {
	case "386":
		return "x86"
	case "amd64":
		return "x64"
	default:
		return runtime.GOARCH
	}
}

func getValue(u interface{}, def string) string {
	if v, ok := u.(string); ok {
		return v
	}
	return def
}

func getUname(params []string) (hostname string, err error) {
	if len(params) > 0 {
		return "", errors.New("Too many parameters.")
	}

	opsys, err := wmi.QueryTable(`root\cimv2`, `select CSName,Version,Caption,CSDVersion from Win32_OperatingSystem`)
	if err != nil {
		return "", err
	}
	if len(opsys) < 1 {
		return "", errors.New("Cannot obtain operation system data from WMI.")
	}
	m := opsys[0]

	hostname = "Windows"
	hostname += " " + getValue(m["CSName"], "<unknown nodename>")
	hostname += " " + getValue(m["Version"], "<unknown release>")
	hostname += " " + getValue(m["Caption"], "<unknown version>")
	if m["CSDVersion"] != nil {
		hostname += " " + getValue(m["Caption"], "<unknown csd version>")
	}
	hostname += " " + getArch()
	return
}

func getSwArch(params []string) (swarch string, err error) {
	if len(params) > 0 {
		return "", errors.New("Too many parameters.")
	}

	return getArch(), nil
}
