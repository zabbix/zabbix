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
/* 	KEY			FLAG		FUNCTION 		TEST PARAMETERS */
{
	{"vfs.fs.size",		CF_HAVEPARAMS,	VFS_FS_SIZE,		"/,free"},
	{"vfs.fs.inode",	CF_HAVEPARAMS,	VFS_FS_INODE,		"/,free"},
	{"vfs.fs.discovery",	0,		VFS_FS_DISCOVERY,	NULL},

	{"net.if.in",		CF_HAVEPARAMS,	NET_IF_IN,		"lo0,bytes"},
	{"net.if.out",		CF_HAVEPARAMS,	NET_IF_OUT,		"lo0,bytes"},
	{"net.if.total",	CF_HAVEPARAMS,	NET_IF_TOTAL,		"lo0,bytes"},
	{"net.if.collisions",	CF_HAVEPARAMS,	NET_IF_COLLISIONS,	"lo0"},
	{"net.if.discovery",	0,		NET_IF_DISCOVERY,	NULL},

	{"vm.memory.size",	CF_HAVEPARAMS,	VM_MEMORY_SIZE,		"free"},

	{"proc.num",		CF_HAVEPARAMS,	PROC_NUM,		"inetd"},
	{"proc.mem",		CF_HAVEPARAMS,	PROC_MEM,		"inetd"},

	{"system.cpu.switches",	0,		SYSTEM_CPU_SWITCHES,	NULL},
	{"system.cpu.intr",	0,		SYSTEM_CPU_INTR,	NULL},
	{"system.cpu.util",	CF_HAVEPARAMS,	SYSTEM_CPU_UTIL,	"all,user,avg1"},
	{"system.cpu.load",	CF_HAVEPARAMS,	SYSTEM_CPU_LOAD,	"all,avg1"},
	{"system.cpu.num",	CF_HAVEPARAMS,	SYSTEM_CPU_NUM,		"online"},

	{"system.uname",	0,		SYSTEM_UNAME,		NULL},

	{"system.uptime",	0,		SYSTEM_UPTIME,		NULL},

	{"system.stat",		CF_HAVEPARAMS,	SYSTEM_STAT,		"page,fi"},
	{"system.swap.size",	CF_HAVEPARAMS,	SYSTEM_SWAP_SIZE,	"all,free"},
	{"system.sw.arch",	0,		SYSTEM_SW_ARCH,		NULL},

	{NULL}
};
