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

#include "config.h"
#include "common.h"
#include "sysinfo.h"

ZBX_METRIC	parameters_specific[]=
/* 	KEY			FLAG	FUNCTION 	ADD_PARAM	TEST_PARAM */
	{

	{"agent.ping",		0,	AGENT_PING, 		0,	0},
	{"agent.version",	0,	AGENT_VERSION,		0,	0},

	{"kernel.maxfiles",	0,	KERNEL_MAXFILES,	0,	0},
	{"kernel.maxproc",	0,	KERNEL_MAXPROC, 	0,	0},

	{"vfs.file.exists",	CF_USEUPARAM,	VFS_FILE_EXISTS,	0,	"/etc/passwd"},
	{"vfs.file.time",       CF_USEUPARAM,   VFS_FILE_TIME,          0,      "/etc/passwd,modify"},
	{"vfs.file.size",	CF_USEUPARAM,	VFS_FILE_SIZE, 		0,	"/etc/passwd"},
	{"vfs.file.regexp",	CF_USEUPARAM,	VFS_FILE_REGEXP,	0,	"/etc/passwd,root"},
	{"vfs.file.regmatch",	CF_USEUPARAM,	VFS_FILE_REGMATCH, 	0,	"/etc/passwd,root"},
	{"vfs.fs.size",		CF_USEUPARAM,	VFS_FS_SIZE,	0,	"/,free"},
	{"vfs.fs.inode",	CF_USEUPARAM,	VFS_FS_INODE,	0,	"/,free"},

	{"vfs.dev.read",	CF_USEUPARAM,	VFS_DEV_READ,	0,	"hda,ops,avg1"},
	{"vfs.dev.write",	CF_USEUPARAM,	VFS_DEV_WRITE,	0,	"hda,ops,avg1"},

	{"vfs.file.cksum",	CF_USEUPARAM,	VFS_FILE_CKSUM,		0,	"/etc/services"},
	{"vfs.file.md5sum",	CF_USEUPARAM,	VFS_FILE_MD5SUM,	0,	"/etc/services"},

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

	{"proc.num",		CF_USEUPARAM,	PROC_NUM,	0,	"inetd,,"},
	{"proc.mem",		CF_USEUPARAM,	PROC_MEMORY,	0,	"inetd,,"},

	{"system.cpu.switches", 0,              SYSTEM_CPU_SWITCHES,    0,      0},
	{"system.cpu.intr",     0,              SYSTEM_CPU_INTR,        0,      0},
	{"system.cpu.util",	CF_USEUPARAM,	SYSTEM_CPU_UTIL,	0,	"all,user,avg1"},
	{"system.cpu.load",	CF_USEUPARAM,	SYSTEM_CPU_LOAD,	0,	"all,avg1"},

	{"system.swap.size",	CF_USEUPARAM,	SYSTEM_SWAP_SIZE,	0,	"all,free"},
	{"system.swap.in",      CF_USEUPARAM,   SYSTEM_SWAP_IN,         0,      "all"},
	{"system.swap.out",     CF_USEUPARAM,   SYSTEM_SWAP_OUT,        0,      "all,count"},	

	{"system.hostname",	0,	SYSTEM_HOSTNAME,	0,	0},

	{"system.uname",	0,	SYSTEM_UNAME,		0,	0},
	{"system.uptime",	0,	SYSTEM_UPTIME,		0,	0},
	{"system.users.num",	0,	SYSTEM_UNUM, 		0,	0},

	{0}
	};
