//go:build !linux
// +build !linux

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
#cgo CFLAGS: -I${SRCDIR}/../../../../../include

#include "zbxsysinfo.h"

int	SYSTEM_LOCALTIME(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_boottime(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_tcp_listen(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_TCP_PORT(AGENT_REQUEST *request, AGENT_RESULT *result);
int	CHECK_SERVICE(AGENT_REQUEST *request, AGENT_RESULT *result);
int	CHECK_SERVICE_PERF(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_udp_listen(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_cpu_load(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_USERS_NUM(AGENT_REQUEST *request, AGENT_RESULT *result);
int	VFS_DIR_GET(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_fs_discovery(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_fs_inode(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_fs_size(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_fs_get(AGENT_REQUEST *request, AGENT_RESULT *result);
int	VM_MEMORY_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result);

*/
import "C"

import (
	"unsafe"
)

func resolveMetric(key string) (cfunc unsafe.Pointer) {
	switch key {
	case "system.localtime":
		return unsafe.Pointer(C.SYSTEM_LOCALTIME)
	case "system.boottime":
		return unsafe.Pointer(C.system_boottime)
	case "net.tcp.listen":
		return unsafe.Pointer(C.net_tcp_listen)
	case "net.tcp.port":
		return unsafe.Pointer(C.NET_TCP_PORT)
	case "net.udp.listen":
		return unsafe.Pointer(C.net_udp_listen)
	case "system.cpu.load":
		return unsafe.Pointer(C.system_cpu_load)
	case "vfs.dir.get":
		return unsafe.Pointer(C.VFS_DIR_GET)
	case "vfs.fs.discovery":
		return unsafe.Pointer(C.vfs_fs_discovery)
	case "vfs.fs.inode":
		return unsafe.Pointer(C.vfs_fs_inode)
	case "vfs.fs.size":
		return unsafe.Pointer(C.vfs_fs_size)
	case "vfs.fs.get":
		return unsafe.Pointer(C.vfs_fs_get)
	case "vm.memory.size":
		return unsafe.Pointer(C.VM_MEMORY_SIZE)

	default:
		return
	}
}
