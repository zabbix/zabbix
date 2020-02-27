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

package proc

type procQuery struct {
	name    string
	user    string
	cmdline string
}

const (
	procInfoPid = 1 << iota
	procInfoName
	procInfoUser
	procInfoCmdline
)

type procInfo struct {
	pid     int64
	name    string
	userid  int64
	cmdline string
	arg0    string
}

type cpuUtil struct {
	utime   uint64
	stime   uint64
	started uint64
	err     error
}
