/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
	{"kernel.maxfiles",	0,		KERNEL_MAXFILES,	NULL,	NULL},
	{"kernel.maxproc",	0,		KERNEL_MAXPROC, 	NULL,	NULL},

	{"vfs.fs.size",		CF_USEUPARAM,	VFS_FS_SIZE,		NULL,	"/,free"},
	{"vfs.fs.inode",	CF_USEUPARAM,	VFS_FS_INODE,		NULL,	"/,free"},
	{"vfs.fs.discovery",	0,		VFS_FS_DISCOVERY,	NULL,	NULL},

	{"vm.memory.size",	CF_USEUPARAM,	VM_MEMORY_SIZE,		NULL,	"free"},

	{"net.tcp.listen",      CF_USEUPARAM,   NET_TCP_LISTEN, 	NULL,	"80"},
	{"net.udp.listen",      CF_USEUPARAM,   NET_UDP_LISTEN, 	NULL,	"68"},

	{"net.if.in",		CF_USEUPARAM,	NET_IF_IN,		NULL,	"en0,bytes"},
	{"net.if.out",		CF_USEUPARAM,	NET_IF_OUT,		NULL,	"en0,bytes"},
	{"net.if.total",	CF_USEUPARAM,	NET_IF_TOTAL,		NULL,	"en0,bytes"},
	{"net.if.collisions",   CF_USEUPARAM,   NET_IF_COLLISIONS,      NULL,	"en0"},

	{"system.cpu.num",	CF_USEUPARAM,	SYSTEM_CPU_NUM,		NULL,	"online"},
	{"system.cpu.load",	CF_USEUPARAM,	SYSTEM_CPU_LOAD,	NULL,	"all,avg1"},

	{"system.uptime",	0,		SYSTEM_UPTIME,		NULL,	NULL},
	{"system.boottime",	0,		SYSTEM_BOOTTIME,	NULL,	NULL},

	{0}
};
