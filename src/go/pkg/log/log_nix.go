//go:build !windows
// +build !windows

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

package log

import (
	"fmt"
	"log/syslog"
)

var syslogWriter *syslog.Writer

func createSyslog() (err error) {
	syslogWriter, err = syslog.New(syslog.LOG_WARNING|syslog.LOG_DAEMON, "zabbix_agent2")
	return
}

func procSysLog(format string, args []interface{}, level int) {
	switch level {
	case Info:
		syslogWriter.Info(fmt.Sprintf(format, args...))
	case Crit:
		syslogWriter.Crit(fmt.Sprintf(format, args...))
	case Err:
		syslogWriter.Err(fmt.Sprintf(format, args...))
	case Warning:
		syslogWriter.Warning(fmt.Sprintf(format, args...))
	case Debug, Trace:
		syslogWriter.Debug(fmt.Sprintf(format, args...))
	}
	return
}
