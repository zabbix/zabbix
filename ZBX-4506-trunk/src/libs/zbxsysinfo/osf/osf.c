/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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

	{"vfs.fs.size",		CF_USEUPARAM,	VFS_FS_SIZE,		0,	"/,free"},
	{"vfs.fs.inode",	CF_USEUPARAM,	VFS_FS_INODE,		0,	"/,free"},

	{"vm.memory.size",	CF_USEUPARAM,	VM_MEMORY_SIZE,		0,	"free"},

	{"proc.num",		CF_USEUPARAM,	PROC_NUM,		0,	"inetd,,"},
	{"proc.mem",		CF_USEUPARAM,	PROC_MEM,		0,	"inetd,,"},

	{"system.cpu.util",	CF_USEUPARAM,	SYSTEM_CPU_UTIL,	0,	"all,user,avg1"},
	{"system.cpu.load",	CF_USEUPARAM,	SYSTEM_CPU_LOAD,	0,	"all,avg1"},

	{"system.swap.size",	CF_USEUPARAM,	SYSTEM_SWAP_SIZE,	0,	"all,free"},

	{"system.uptime",	0,		SYSTEM_UPTIME,		0,	0},

	{0}
};
