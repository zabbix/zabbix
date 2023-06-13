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
#include "../specsysinfo.h"

static zbx_metric_t	parameters_specific[] =
/*	KEY			FLAG		FUNCTION		TEST PARAMETERS */
{
	{"kernel.maxfiles",	0,		kernel_maxfiles,	NULL},
	{"kernel.maxproc",	0,		kernel_maxproc,	NULL},

	{"vfs.fs.size",		CF_HAVEPARAMS,	vfs_fs_size,		"/,free"},
	{"vfs.fs.inode",	CF_HAVEPARAMS,	vfs_fs_inode,		"/,free"},

	{"vm.memory.size",	CF_HAVEPARAMS,	vm_memory_size,		"free"},

	{"proc.num",		CF_HAVEPARAMS,	proc_num,		"inetd"},
	{"proc.mem",		CF_HAVEPARAMS,	proc_mem,		"inetd"},

	{"system.cpu.util",	CF_HAVEPARAMS,	system_cpu_util,	"all,user,avg1"},
	{"system.cpu.load",	CF_HAVEPARAMS,	system_cpu_load,	"all,avg1"},
	{"system.cpu.discovery",0,		system_cpu_discovery,	NULL},

	{"system.uname",	0,		system_uname,		NULL},

	{"system.swap.size",	CF_HAVEPARAMS,	system_swap_size,	"all,free"},

	{"system.uptime",	0,		system_uptime,		NULL},
	{"system.sw.arch",	0,		system_sw_arch,		NULL},

	{NULL}
};

zbx_metric_t	*get_parameters_specific(void)
{
	return parameters_specific;
}
