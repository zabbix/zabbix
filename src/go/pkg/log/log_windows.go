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

package log

import (
	"errors"
)

func createSyslog() error {
	return errors.New("syslog is not supported on Windows")
}

func Infof(format string, args ...interface{}) {
	if CheckLogLevel(Info) {
		procLog(format, args)
	}
}

func Critf(format string, args ...interface{}) {
	if CheckLogLevel(Crit) {
		procLog(format, args)
	}
}

func Errf(format string, args ...interface{}) {
	if CheckLogLevel(Err) {
		procLog(format, args)
	}
}

func Warningf(format string, args ...interface{}) {
	if CheckLogLevel(Warning) {
		procLog(format, args)
	}
}

func Tracef(format string, args ...interface{}) {
	if CheckLogLevel(Trace) {
		procLog(format, args)
	}
}

func Debugf(format string, args ...interface{}) {
	if CheckLogLevel(Debug) {
		procLog(format, args)
	}
}
