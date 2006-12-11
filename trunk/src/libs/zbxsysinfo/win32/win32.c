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

/*
SKIP	{ "__exec{*}",				NULL,			H_Execute,		NULL },
SKIP	{ "__usercnt{*}",			H_UserCounter,		NULL,			NULL },
OK	{ "system.run[*]",			NULL,			H_RunCommand,		NULL },
SKIP	{ "agent.stat[avg_collector_time]",	H_NumericPtr,		NULL,			(char *)&statAvgCollectorTime },
SKIP	{ "agent.stat[max_collector_time]",	H_NumericPtr,		NULL,			(char *)&statMaxCollectorTime },
SKIP	{ "agent.stat[accepted_requests]",	H_NumericPtr,		NULL,			(char *)&statAcceptedRequests },
SKIP	{ "agent.stat[rejected_requests]",	H_NumericPtr,		NULL,			(char *)&statRejectedRequests },
SKIP	{ "agent.stat[accept_errors]",		H_NumericPtr,		NULL,			(char *)&statAcceptErrors },
SKIP	{ "agent.stat[processed_requests]",	H_NumericPtr,		NULL,			(char *)&statProcessedRequests },
SKIP	{ "agent.stat[failed_requests]",	H_NumericPtr,		NULL,			(char *)&statFailedRequests },
SKIP	{ "agent.stat[unsupported_requests]",	H_NumericPtr,		NULL,			(char *)&statUnsupportedRequests },
OK	{ "proc_info[*]",			H_ProcInfo,		NULL,			NULL }, // TODO 'new realization and naming'
OK	{ "perf_counter[*]",			H_PerfCounter,		NULL,			NULL }, // TODO 'new naming'
OK	{ "service_state[*]",			H_ServiceState,		NULL,			NULL }, // TODO 'new naming'

OK	{ "net.tcp.port[*]",			H_CheckTcpPort,		NULL,			NULL },
OK	{ "system.cpu.util[*]",			H_CpuUtil,		NULL,			NULL},
OK	{ "system.cpu.load[*]",			H_CpuLoad,		NULL,			NULL},
OK	{ "vfs.fs.size[*]",			H_DiskInfo,		NULL,			NULL },
OK	{ "vfs.file.size[*]",			H_FileSize,		NULL,			NULL },
OK	{ "vfs.file.cksum[*]",			H_CRC32,		NULL,			NULL },
OK	{ "vfs.file.md5sum[*]",			NULL,			H_MD5Hash,		NULL },
OK	{ "system.swap.size[*]",		H_SwapSize,		NULL,			NULL },
OK	{ "vm.memory.size[*]",			H_MemorySize,		NULL,			NULL },
OK	{ "agent.ping",				H_NumericConstant,	NULL,			(char *)1 },
OK	{ "proc.num[*]",			H_ProcNum,		NULL,			NULL },
OK	{ "system.uname",			NULL,			H_SystemUname,		NULL },
OK	{ "system.hostname",			NULL,			H_HostName,		NULL },
OK	{ "agent.version",			NULL,			H_StringConstant,	AGENT_VERSION },
*/

ZBX_METRIC	parameters_specific[]=
/* 	KEY			FLAG	FUNCTION 	ADD_PARAM	TEST_PARAM */
	{

	{"agent.ping",		0,	AGENT_PING, 		0,	0},
	{"agent.version",	0,	AGENT_VERSION,		0,	0},

	{"kernel.maxfiles",	0,	KERNEL_MAXFILES,	0,	0},
	{"kernel.maxproc",	0,	KERNEL_MAXPROC, 	0,	0},

	{"vfs.file.exists",	CF_USEUPARAM,	VFS_FILE_EXISTS,	0,	"c:\\windows\\win.ini"},
	{"vfs.file.time",       CF_USEUPARAM,   VFS_FILE_TIME,          0,      "c:\\windows\\win.ini,modify"},
	{"vfs.file.size",	CF_USEUPARAM,	VFS_FILE_SIZE, 		0,	"c:\\windows\\win.ini"},
	{"vfs.file.regexp",	CF_USEUPARAM,	VFS_FILE_REGEXP,	0,	"c:\\windows\\win.ini,fonts"},
	{"vfs.file.regmatch",	CF_USEUPARAM,	VFS_FILE_REGMATCH, 	0,	"c:\\windows\\win.ini,fonts"},
	{"vfs.file.cksum",	CF_USEUPARAM,	VFS_FILE_CKSUM,		0,	"c:\\windows\\win.ini"},
	{"vfs.file.md5sum",	CF_USEUPARAM,	VFS_FILE_MD5SUM,	0,	"c:\\windows\\win.ini"},

	{"vfs.fs.size",		CF_USEUPARAM,	VFS_FS_SIZE,	0,	"c:,free"},
	{"vfs.fs.inode",	CF_USEUPARAM,	VFS_FS_INODE,	0,	"c:,free"},

	{"vfs.dev.read",	CF_USEUPARAM,	VFS_DEV_READ,	0,	"hda,ops,avg1"},
	{"vfs.dev.write",	CF_USEUPARAM,	VFS_DEV_WRITE,	0,	"hda,ops,avg1"},

	{"net.tcp.dns",		CF_USEUPARAM,	CHECK_DNS,		0,	"127.0.0.1,localhost"},
	{"net.tcp.listen",      CF_USEUPARAM,   NET_TCP_LISTEN, 0,      "80"},	
	{"net.tcp.port",	CF_USEUPARAM,	CHECK_PORT,		0,	",80"},
	{"net.tcp.service",	CF_USEUPARAM,	CHECK_SERVICE, 		0,	"ssh,127.0.0.1,22"},
	{"net.tcp.service.perf",CF_USEUPARAM,	CHECK_SERVICE_PERF, 	0,	"ssh,127.0.0.1,22"},

	{"net.if.in",		CF_USEUPARAM,	NET_IF_IN,	0,	"lo,bytes"},
	{"net.if.out",		CF_USEUPARAM,	NET_IF_OUT,	0,	"lo,bytes"},
	{"net.if.total",	CF_USEUPARAM,	NET_IF_TOTAL,	0,	"lo,bytes"},
        {"net.if.collisions",   CF_USEUPARAM,   NET_IF_COLLISIONS,      0,      "lo"},

	{"vm.memory.size",	CF_USEUPARAM,	VM_MEMORY_SIZE,	0,	"free"},

	{"proc.num",		CF_USEUPARAM,	PROC_NUM,	0,	"svchost.exe,"},
	{"proc.mem",		CF_USEUPARAM,	PROC_MEMORY,	0,	"svchost.exe,,"},

	{"system.cpu.switches", 0,              SYSTEM_CPU_SWITCHES,    0,      0},
	{"system.cpu.intr",     0,              SYSTEM_CPU_INTR,        0,      0},
	{"system.cpu.util",	CF_USEUPARAM,	SYSTEM_CPU_UTIL,	0,	"all,system,avg1"},
	{"system.cpu.load",	CF_USEUPARAM,	SYSTEM_CPU_LOAD,	0,	"all,avg1"},

	{"system.swap.size",	CF_USEUPARAM,	SYSTEM_SWAP_SIZE,	0,	"all,free"},
	{"system.swap.in",      CF_USEUPARAM,   SYSTEM_SWAP_IN,       	0,      "all"},
	{"system.swap.out",     CF_USEUPARAM,   SYSTEM_SWAP_OUT,	0,      "all,count"},	

	{"system.hostname",	0,	SYSTEM_HOSTNAME,	0,	0},

	{"system.uname",	0,	SYSTEM_UNAME,		0,	0},
	{"system.uptime",	0,	SYSTEM_UPTIME,		0,	0},
	{"system.users.num",	0,	SYSTEM_UNUM, 		0,	0},

	{"service_state",	CF_USEUPARAM,	SERVICE_STATE,	0,	ZABBIX_SERVICE_NAME},
	{"perf_counter",	CF_USEUPARAM,	PERF_MONITOR,	0,	"\\System\\Processes"},
	{"proc_info",		CF_USEUPARAM,	PROC_INFO,	0,	"svchost.exe"},

	{0}
	};
