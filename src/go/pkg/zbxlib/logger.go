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

package zbxlib

import (
	"C"
)
import "golang.zabbix.com/sdk/log"

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
