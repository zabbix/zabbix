/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "sysinfo.h"

#include "service.h"

ZBX_METRIC	parameters_specific[] =
/* 	KEY			FLAG		FUNCTION 	ADD_PARAM	TEST_PARAM */
{
	{"vfs.fs.size",		CF_USEUPARAM,	VFS_FS_SIZE,		0,	"c:,free"},

	{"net.tcp.listen",      CF_USEUPARAM,   NET_TCP_LISTEN, 	0,      "80"},

	{"net.if.in",		CF_USEUPARAM,	NET_IF_IN,		0,	"MS TCP Loopback interface,bytes"},
	{"net.if.out",		CF_USEUPARAM,	NET_IF_OUT,		0,	"MS TCP Loopback interface,bytes"},
	{"net.if.total",	CF_USEUPARAM,	NET_IF_TOTAL,		0,	"MS TCP Loopback interface,bytes"},
	{"net.if.list",		0,		NET_IF_LIST,		0,	0},

	{"vm.memory.size",	CF_USEUPARAM,	VM_MEMORY_SIZE,		0,	"free"},

	{"proc.num",		CF_USEUPARAM,	PROC_NUM,		0,	"svchost.exe,"},

	{"system.cpu.util",	CF_USEUPARAM,	SYSTEM_CPU_UTIL,	0,	"all,system,avg1"},
	{"system.cpu.load",	CF_USEUPARAM,	SYSTEM_CPU_LOAD,	0,	"all,avg1"},
	{"system.cpu.num",	CF_USEUPARAM,	SYSTEM_CPU_NUM,		0,	"online"},

	{"system.swap.size",	CF_USEUPARAM,	SYSTEM_SWAP_SIZE,	0,	"all,free"},

	{"system.uptime",	0,		SYSTEM_UPTIME,		0,	0},

	{"service_state",	CF_USEUPARAM,	SERVICE_STATE,		0,	ZABBIX_SERVICE_NAME},
	{"services",		CF_USEUPARAM,	SERVICES,		0,	0},
	{"perf_counter",	CF_USEUPARAM,	PERF_COUNTER,		0,	"\\System\\Processes"},
	{"proc_info",		CF_USEUPARAM,	PROC_INFO,		0,	"svchost.exe"},

	{"__UserPerfCounter",	CF_USEUPARAM,	USER_PERF_COUNTER,	0,	""},

	{0}
};
