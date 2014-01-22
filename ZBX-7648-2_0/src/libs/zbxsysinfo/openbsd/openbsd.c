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
/* 	KEY			FLAG		FUNCTION 	ADD_PARAM	TEST_PARAM */
{
	{"kernel.maxfiles",	0,		KERNEL_MAXFILES,	0,	0},
	{"kernel.maxproc",	0,		KERNEL_MAXPROC, 	0,	0},

	{"vfs.fs.size",		CF_USEUPARAM,	VFS_FS_SIZE, 		0,	"/"},
	{"vfs.fs.inode",	CF_USEUPARAM,	VFS_FS_INODE,		0,	"/,free"},
	{"vfs.fs.discovery",	0,		VFS_FS_DISCOVERY,	0,	0},

	{"vfs.dev.read",	CF_USEUPARAM,	VFS_DEV_READ,		NULL,	"sd0,operations"},
	{"vfs.dev.write",	CF_USEUPARAM,	VFS_DEV_WRITE,		NULL,	"sd0,operations"},

	{"net.if.in",		CF_USEUPARAM,	NET_IF_IN,		0,	"lo0,bytes"},
	{"net.if.out",		CF_USEUPARAM,	NET_IF_OUT,		0,	"lo0,bytes"},
	{"net.if.total",	CF_USEUPARAM,	NET_IF_TOTAL,		0,	"lo0,bytes"},
	{"net.if.collisions",   CF_USEUPARAM,   NET_IF_COLLISIONS,      0,      "lo0"},

	{"vm.memory.size",	CF_USEUPARAM,	VM_MEMORY_SIZE,		0,	"free"},

	{"proc.num",		CF_USEUPARAM,	PROC_NUM,		0,	"inetd,,"},
	{"proc.mem",		CF_USEUPARAM,	PROC_MEM,		0,	"inetd,,"},

	{"system.cpu.switches", 0,              SYSTEM_CPU_SWITCHES,    0,      0},
	{"system.cpu.intr",     0,              SYSTEM_CPU_INTR,        0,      0},
	{"system.cpu.util",	CF_USEUPARAM,	SYSTEM_CPU_UTIL,	0,	"all,idle"},
	{"system.cpu.load",	CF_USEUPARAM,	SYSTEM_CPU_LOAD,	0,	"all,avg1"},
	{"system.cpu.num",	CF_USEUPARAM,	SYSTEM_CPU_NUM,		0,	"online"},

	{"system.swap.size",	CF_USEUPARAM,	SYSTEM_SWAP_SIZE,	0,	"all,free"},
	{"system.swap.in",      CF_USEUPARAM,   SYSTEM_SWAP_IN,         0,      "all,pages"},
	{"system.swap.out",     CF_USEUPARAM,   SYSTEM_SWAP_OUT,        0,      "all,count"},

	{"system.uptime",	0,		SYSTEM_UPTIME,		0,	0},
	{"system.boottime",     0,      	SYSTEM_BOOTTIME,        0,      0},

	{"sensor",		CF_USEUPARAM,	GET_SENSOR,		0,	"cpu0,temp0"},

	{0}
};
