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

ZBX_METRIC	parameters_specific[] =
/* 	KEY			FLAG		FUNCTION 	ADD_PARAM	TEST_PARAM */
{
	{"vfs.fs.size",		CF_USEUPARAM,	VFS_FS_SIZE,		0,	"/,free"},
	{"vfs.fs.inode",	CF_USEUPARAM,	VFS_FS_INODE,		0,	"/,free"},

	{"vm.memory.size",	CF_USEUPARAM,	VM_MEMORY_SIZE,		0,	"free"},

	{"system.cpu.util",	CF_USEUPARAM,	SYSTEM_CPU_UTIL,	0,	"all,user,avg1"},
	{"system.cpu.load",	CF_USEUPARAM,	SYSTEM_CPU_LOAD,	0,	"all,avg1"},
	{"system.cpu.num",	CF_USEUPARAM,	SYSTEM_CPU_NUM,		0,	"online"},

	{0}
};
