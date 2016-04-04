/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

#include "service.h"

ZBX_METRIC	parameters_specific[] =
/* 	KEY			FLAG		FUNCTION 	ADD_PARAM	TEST_PARAM */
{
	{"vfs.fs.size",		CF_USEUPARAM,	VFS_FS_SIZE,		NULL,	"c:,free"},
	{"vfs.fs.discovery",	0,		VFS_FS_DISCOVERY,	NULL,	NULL},

	{"net.tcp.listen",	CF_USEUPARAM,	NET_TCP_LISTEN,		NULL,	"80"},

	{"net.if.in",		CF_USEUPARAM,	NET_IF_IN,		NULL,	"MS TCP Loopback interface,bytes"},
	{"net.if.out",		CF_USEUPARAM,	NET_IF_OUT,		NULL,	"MS TCP Loopback interface,bytes"},
	{"net.if.total",	CF_USEUPARAM,	NET_IF_TOTAL,		NULL,	"MS TCP Loopback interface,bytes"},
	{"net.if.discovery",	0,		NET_IF_DISCOVERY,	NULL,	NULL},
	{"net.if.list",		0,		NET_IF_LIST,		NULL,	NULL},

	{"vm.memory.size",	CF_USEUPARAM,	VM_MEMORY_SIZE,		NULL,	"free"},

	{"proc.num",		CF_USEUPARAM,	PROC_NUM,		NULL,	"svchost.exe,"},

	{"system.cpu.util",	CF_USEUPARAM,	SYSTEM_CPU_UTIL,	NULL,	"all,system,avg1"},
	{"system.cpu.load",	CF_USEUPARAM,	SYSTEM_CPU_LOAD,	NULL,	"all,avg1"},
	{"system.cpu.num",	CF_USEUPARAM,	SYSTEM_CPU_NUM,		NULL,	"online"},

	{"system.swap.size",	CF_USEUPARAM,	SYSTEM_SWAP_SIZE,	NULL,	"all,free"},

	{"system.uptime",	0,		SYSTEM_UPTIME,		NULL,	NULL},

	{"service_state",	CF_USEUPARAM,	SERVICE_STATE,		NULL,	ZABBIX_SERVICE_NAME},
	{"services",		CF_USEUPARAM,	SERVICES,		NULL,	NULL},
	{"perf_counter",	CF_USEUPARAM,	PERF_COUNTER,		NULL,	"\\System\\Processes"},
	{"proc_info",		CF_USEUPARAM,	PROC_INFO,		NULL,	"svchost.exe"},

	{"__UserPerfCounter",	CF_USEUPARAM,	USER_PERF_COUNTER,	NULL,	""},

	{0}
};
