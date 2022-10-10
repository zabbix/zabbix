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

/* cspell:disable */

/*
#cgo CFLAGS: -I${SRCDIR}/../../../../../include -I${SRCDIR}/../../../../build/win32/include

#include "zbxsysinfo.h"
#include "module.h"

int	SYSTEM_LOCALTIME(AGENT_REQUEST *request, AGENT_RESULT *result);
int	PROC_MEM(AGENT_REQUEST *request, AGENT_RESULT *result);
int	PROC_NUM(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_boottime(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_tcp_listen(AGENT_REQUEST *request, AGENT_RESULT *result);
int	CHECK_SERVICE(AGENT_REQUEST *request, AGENT_RESULT *result);
int	CHECK_SERVICE_PERF(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_udp_listen(AGENT_REQUEST *request, AGENT_RESULT *result);
int	get_sensor(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_cpu_load(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_cpu_switches(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_cpu_intr(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_hw_chassis(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_hw_cpu(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_hw_devices(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_hw_macaddr(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_sw_os(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_sw_packages(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_swap_in(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_swap_out(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_swap_size(AGENT_REQUEST *request, AGENT_RESULT *result);
int	VFS_DIR_GET(AGENT_REQUEST *request, AGENT_RESULT *result);
int	VFS_FS_INODE(AGENT_REQUEST *request, AGENT_RESULT *result);
int	VM_MEMORY_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result);
*/
import "C"

import (
	"unsafe"
)

func resolveMetric(key string) (cfunc unsafe.Pointer) {
	switch key {
	case "system.localtime":
		cfunc = unsafe.Pointer(C.SYSTEM_LOCALTIME)
	case "vfs.dir.get":
		cfunc = unsafe.Pointer(C.VFS_DIR_GET)
	}

	return
}
