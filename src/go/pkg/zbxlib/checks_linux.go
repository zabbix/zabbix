/*
** Copyright (C) 2001-2026 Zabbix SIA
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

/* cspell:disable */

/*
#cgo CFLAGS: -I${SRCDIR}/../../../../../include -I${SRCDIR}/../../../../src/libs

#include "zbxsysinfo/sysinfo.h"

// vfs_dir_get
#include "zbxsysinfo/common/vfs_file.c"
#include "zbxsysinfo/common/dir.c"
*/
import "C"

import (
	"unsafe"
)

func resolveMetric(key string) (cfunc unsafe.Pointer) {
	switch key {
	case "net.tcp.listen":
		cfunc = unsafe.Pointer(C.net_tcp_listen)
	case "sensor":
		cfunc = unsafe.Pointer(C.get_sensor)
	case "system.cpu.load":
		cfunc = unsafe.Pointer(C.system_cpu_load)
	case "system.cpu.switches":
		cfunc = unsafe.Pointer(C.system_cpu_switches)
	case "system.cpu.intr":
		cfunc = unsafe.Pointer(C.system_cpu_intr)
	case "system.hw.cpu":
		cfunc = unsafe.Pointer(C.system_hw_cpu)
	case "system.hw.macaddr":
		cfunc = unsafe.Pointer(C.system_hw_macaddr)
	case "vfs.dir.get":
		cfunc = unsafe.Pointer(C.vfs_dir_get)
	}
	return
}
