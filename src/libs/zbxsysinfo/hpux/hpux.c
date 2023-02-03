/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "zbxsysinfo.h"
#include "../sysinfo.h"

ZBX_METRIC	parameters_specific[] =
/*	KEY			FLAG		FUNCTION		TEST PARAMETERS */
{
	{"vfs.fs.size",		CF_HAVEPARAMS,	vfs_fs_size,		"/,free"},
	{"vfs.fs.inode",	CF_HAVEPARAMS,	vfs_fs_inode,		"/,free"},
	{"vfs.fs.discovery",	0,		vfs_fs_discovery,	NULL},
	{"vfs.fs.get",		0,		vfs_fs_get,		NULL},

	{"net.if.discovery",	0,		net_if_discovery,	NULL},
	{"net.if.in",		CF_HAVEPARAMS,	net_if_in,		"lan0,bytes"},
	{"net.if.out",		CF_HAVEPARAMS,	net_if_out,		"lan0,bytes"},
	{"net.if.total",	CF_HAVEPARAMS,	net_if_total,		"lan0,bytes"},

	{"vm.memory.size",	CF_HAVEPARAMS,	vm_memory_size,		"free"},

	{"proc.num",		CF_HAVEPARAMS,  proc_num,		"inetd"},

	{"system.cpu.util",	CF_HAVEPARAMS,	system_cpu_util,	"all,user,avg1"},
	{"system.cpu.load",	CF_HAVEPARAMS,	system_cpu_load,	"all,avg1"},
	{"system.cpu.num",	CF_HAVEPARAMS,	system_cpu_num,		"online"},
	{"system.cpu.discovery",0,		system_cpu_discovery,	NULL},

	{"system.uname",	0,		system_uname,		NULL},
	{"system.sw.arch",	0,		system_sw_arch,		NULL},

	{NULL}
};
