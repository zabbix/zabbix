/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#include "common.h"
#include "sysinfo.h"

ZBX_METRIC	parameters_specific[] =
/*	KEY			FLAG		FUNCTION	ADD_PARAM	TEST_PARAM */
{
	{"kernel.maxfiles",	0,		KERNEL_MAXFILES,	NULL,	NULL},
	{"kernel.maxproc",	0,		KERNEL_MAXPROC,		NULL,	NULL},

	{"vfs.fs.size",		CF_USEUPARAM,	VFS_FS_SIZE,		NULL,	"/,free"},
	{"vfs.fs.inode",	CF_USEUPARAM,	VFS_FS_INODE,		NULL,	"/,free"},
	{"vfs.fs.discovery",	0,		VFS_FS_DISCOVERY,	NULL,	NULL},

	{"vfs.dev.read",	CF_USEUPARAM,	VFS_DEV_READ,		NULL,	"sda,operations"},
	{"vfs.dev.write",	CF_USEUPARAM,	VFS_DEV_WRITE,		NULL,	"sda,operations"},

	{"net.tcp.listen",	CF_USEUPARAM,	NET_TCP_LISTEN,		NULL,	"80"},
	{"net.udp.listen",	CF_USEUPARAM,	NET_UDP_LISTEN,		NULL,	"68"},

	{"net.if.in",		CF_USEUPARAM,	NET_IF_IN,		NULL,	"lo,bytes"},
	{"net.if.out",		CF_USEUPARAM,	NET_IF_OUT,		NULL,	"lo,bytes"},
	{"net.if.total",	CF_USEUPARAM,	NET_IF_TOTAL,		NULL,	"lo,bytes"},
	{"net.if.collisions",	CF_USEUPARAM,	NET_IF_COLLISIONS,	NULL,	"lo"},
	{"net.if.discovery",	0,		NET_IF_DISCOVERY,	NULL,	NULL},

	{"vm.memory.size",	CF_USEUPARAM,	VM_MEMORY_SIZE,		NULL,	"total"},

	{"proc.num",		CF_USEUPARAM,	PROC_NUM,		NULL,	"inetd,,,"},
	{"proc.mem",		CF_USEUPARAM,	PROC_MEM,		NULL,	"inetd,,"},

	{"system.cpu.switches", 0,		SYSTEM_CPU_SWITCHES,	NULL,	NULL},
	{"system.cpu.intr",	0,		SYSTEM_CPU_INTR,	NULL,	NULL},
	{"system.cpu.util",	CF_USEUPARAM,	SYSTEM_CPU_UTIL,	NULL,	"all,user,avg1"},
	{"system.cpu.load",	CF_USEUPARAM,	SYSTEM_CPU_LOAD,	NULL,	"all,avg1"},
	{"system.cpu.num",	CF_USEUPARAM,	SYSTEM_CPU_NUM,		NULL,	"online"},

	{"system.hw.chassis",	CF_USEUPARAM,	SYSTEM_HW_CHASSIS,	NULL,	NULL},
	{"system.hw.cpu",	CF_USEUPARAM,	SYSTEM_HW_CPU,		NULL,	NULL},
	{"system.hw.devices",	CF_USEUPARAM,	SYSTEM_HW_DEVICES,	NULL,	NULL},
	{"system.hw.macaddr",	CF_USEUPARAM,	SYSTEM_HW_MACADDR,	NULL,	NULL},

	{"system.sw.arch",	0,		SYSTEM_SW_ARCH,		NULL,	NULL},
	{"system.sw.os",	CF_USEUPARAM,	SYSTEM_SW_OS,		NULL,	NULL},
	{"system.sw.packages",	CF_USEUPARAM,	SYSTEM_SW_PACKAGES,	NULL,	NULL},

	{"system.swap.size",	CF_USEUPARAM,	SYSTEM_SWAP_SIZE,	NULL,	"all,free"},
	{"system.swap.in",	CF_USEUPARAM,	SYSTEM_SWAP_IN,		NULL,	"all"},
	{"system.swap.out",	CF_USEUPARAM,	SYSTEM_SWAP_OUT,	NULL,	"all"},

	{"system.uptime",	0,		SYSTEM_UPTIME,		NULL,	NULL},
	{"system.boottime",	0,		SYSTEM_BOOTTIME,	NULL,	NULL},

	{"sensor",		CF_USEUPARAM,	GET_SENSOR,		NULL,	"w83781d-i2c-0-2d,temp1"},

	{0}
};
