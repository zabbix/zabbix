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

/* cspell:disable */

/*
#cgo CFLAGS: -I${SRCDIR}/../../../../../include -I${SRCDIR}/../../../../build/win32/include

#include "zbxsysinfo.h"
#include "module.h"

int	system_localtime(AGENT_REQUEST *request, AGENT_RESULT *result);
int	proc_mem(AGENT_REQUEST *request, AGENT_RESULT *result);
int	proc_num(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_boottime(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_tcp_listen(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_service(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_service_perf(AGENT_REQUEST *request, AGENT_RESULT *result);
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
int	vfs_dir_get(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_fs_inode(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vm_memory_size(AGENT_REQUEST *request, AGENT_RESULT *result);
*/
import "C"

import (
	"unsafe"
)

func resolveMetric(key string) (cfunc unsafe.Pointer) {
	switch key {
	case "system.localtime":
		cfunc = unsafe.Pointer(C.system_localtime)
	case "vfs.dir.get":
		cfunc = unsafe.Pointer(C.vfs_dir_get)
	}

	return
}
