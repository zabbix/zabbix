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

package zbxlib

import (
	"C"
)
import "zabbix.com/pkg/log"

const c_info = 127

//export handleZabbixLog
func handleZabbixLog(clevel C.int, cmessage *C.char) {
	message := C.GoString(cmessage)
	switch int(clevel) {
	case log.None:
	case log.Info, c_info:
		log.Infof(message)
	case log.Crit:
		log.Critf(message)
	case log.Err:
		log.Errf(message)
	case log.Warning:
		log.Warningf(message)
	case log.Debug:
		log.Debugf(message)
	case log.Trace:
		log.Tracef(message)
	}
}
