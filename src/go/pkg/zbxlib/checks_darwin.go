//go:build !linux
// +build !linux

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
#cgo CFLAGS: -I${SRCDIR}/../../../../../include

#include "zbxsysinfo.h"

int	system_localtime(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_boottime(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_tcp_listen(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_tcp_port(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_service(AGENT_REQUEST *request, AGENT_RESULT *result);
int	check_service_perf(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_udp_listen(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_cpu_load(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_users_num(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_dir_get(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_fs_discovery(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_fs_inode(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_fs_size(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_fs_get(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vm_memory_size(AGENT_REQUEST *request, AGENT_RESULT *result);

*/
import "C"

import (
	"unsafe"
)

func resolveMetric(key string) (cfunc unsafe.Pointer) {
	switch key {
	case "system.localtime":
		return unsafe.Pointer(C.system_localtime)
	case "system.boottime":
		return unsafe.Pointer(C.system_boottime)
	case "net.tcp.listen":
		return unsafe.Pointer(C.net_tcp_listen)
	case "net.tcp.port":
		return unsafe.Pointer(C.net_tcp_port)
	case "net.udp.listen":
		return unsafe.Pointer(C.net_udp_listen)
	case "system.cpu.load":
		return unsafe.Pointer(C.system_cpu_load)
	case "vfs.dir.get":
		return unsafe.Pointer(C.vfs_dir_get)
	case "vfs.fs.discovery":
		return unsafe.Pointer(C.vfs_fs_discovery)
	case "vfs.fs.inode":
		return unsafe.Pointer(C.vfs_fs_inode)
	case "vfs.fs.size":
		return unsafe.Pointer(C.vfs_fs_size)
	case "vfs.fs.get":
		return unsafe.Pointer(C.vfs_fs_get)
	case "vm.memory.size":
		return unsafe.Pointer(C.vm_memory_size)

	default:
		return
	}
}
