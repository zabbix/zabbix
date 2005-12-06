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

/* Outdated */
/* incorrect OLD naming */
	{"cpu",			CF_USEUPARAM,	OLD_CPU, 	0,	"idle1"},
/*
	{"cpu[idle1]",		0,	0, 	0,	0},
	{"cpu[idle5]",		0,	0, 	0,	0},
	{"cpu[idle15]",		0,	0, 	0,	0},
	{"cpu[nice1]",		0,	0, 	0,	0},
	{"cpu[nice5]",		0,	0, 	0,	0},
	{"cpu[nice15]",		0,	0, 	0,	0},
	{"cpu[system1]",	0,	0, 	0,	0},
	{"cpu[system5]",	0,	0, 	0,	0},
	{"cpu[system15]",	0,	0, 	0,	0},
	{"cpu[user1]",		0,	0, 	0,	0},
	{"cpu[user5]",		0,	0, 	0,	0},
	{"cpu[user15]",		0,	0, 	0,	0},
*/
	{"io",			CF_USEUPARAM,	OLD_IO,  	0,	"disk_io"},
/*
	{"io[disk_io]",		0,	0,  	0,	0},
	{"io[disk_rio]",	0,	0, 	0,	0},
	{"io[disk_wio]",	0,	0, 	0,	0},
	{"io[disk_rblk]",	0,	0, 	0,	0},
	{"io[disk_wblk]",	0,	0, 	0,	0},
*/
	{"kern",		CF_USEUPARAM,	OLD_KERNEL,	0,	"maxfiles"},
/*
	{"kern[maxfiles]",	0,	0,	0,	0},
	{"kern[maxproc]",	0,	0,	0,	0},
*/
	{"memory",		CF_USEUPARAM,	OLD_MEMORY,	0,	"total"},
/*
	{"memory[buffers]",	0,	0,	0,	0},
	{"memory[cached]",	0,	0, 	0,	0},
	{"memory[free]",	0,	0, 	0,	0},
	{"memory[shared]",	0,	0, 	0,	0},
	{"memory[total]",	0,	0,	0,	0},
*/
	{"system",		CF_USEUPARAM,	OLD_SYSTEM, 	0,	"uname"},
/*
	{"system[proccount]",	0,	0, 	0,	0},

	{"system[procload]",	0,	0, 	0,	0},
	{"system[procload5]",	0,	0, 	0,	0},
	{"system[procload15]",	0,	0, 	0,	0},
	{"system[hostname]",	0,	0, 	0,	0},
	{"system[uname]",	0,	0, 	0,	0},
	{"system[uptime]",	0,	0,	0,	0},
	{"system[users]",	0,	0, 	0,	0},

	{"system[procrunning]",	0,	0, 	0,	0},
*/
	{"sensor",		CF_USEUPARAM,	OLD_SENSOR, 	0,	"temp1"},
/*
	{"sensor[temp1]",	0,	0, 	0,	0},
	{"sensor[temp2]",	0,	0, 	0,	0},
	{"sensor[temp3]",	0,	0, 	0,	0},
*/	
	{"swap",		CF_USEUPARAM,	OLD_SWAP,	0,	"total"},
/*
	{"swap[free]",		0,	0,	0,	0},
	{"swap[total]",		0,	0,	0,	0},
*/
	{"version",		CF_USEUPARAM,	OLD_VERSION,	0,	"zabbix_agent"},
/*
	{"version[zabbix_agent]",	0,	OLD_VERSION, 		0,	0},
*/
/* correct OLD naming */	
/*
	{"cksum",		CF_USEUPARAM,	VFS_FILE_CKSUM, 	0,	"/etc/services"},
*/
/*
 	{"diskfree",		CF_USEUPARAM,	VFS_FS_FREE,		0,	"/"},
	{"disktotal",		CF_USEUPARAM,	VFS_FS_TOTAL,		0,	"/"},
	{"diskused",		CF_USEUPARAM,	VFS_FS_USED,		0,	"/"},
	{"diskfree_perc",	CF_USEUPARAM,	VFS_FS_PFREE,		0,	"/"},
	{"diskused_perc",	CF_USEUPARAM,	VFS_FS_PUSED,		0,	"/"},
*/
/*
	{"file",		CF_USEUPARAM,	VFS_FILE_EXISTS,	0,	"/etc/passwd"},
	{"filesize",		CF_USEUPARAM,	VFS_FILE_SIZE, 		0,	"/etc/passwd"},
*/
/*
	{"inodefree",		CF_USEUPARAM,	VFS_FS_INODE_FREE,	0,	"/"},
	{"inodetotal",		CF_USEUPARAM,	VFS_FS_INODE_TOTAL,	0,	"/"},
	{"inodefree_perc",	CF_USEUPARAM,	VFS_FS_INODE_PFREE,	0,	"/"},
*/
/*
	{"md5sum",		CF_USEUPARAM,	VFS_FILE_MD5SUM,	0,	"/etc/services"},
*/
/*
	{"netloadin1",		CF_USEUPARAM,	NET_IF_IBYTES1,		0,	"lo"},
	{"netloadin5",		CF_USEUPARAM,	NET_IF_IBYTES5,		0,	"lo"},
	{"netloadin15",		CF_USEUPARAM,	NET_IF_IBYTES15,	0,	"lo"},
	{"netloadout1",		CF_USEUPARAM,	NET_IF_OBYTES1,		0,	"lo"},
	{"netloadout5",		CF_USEUPARAM,	NET_IF_OBYTES5, 	0,	"lo"},
	{"netloadout15",	CF_USEUPARAM,	NET_IF_OBYTES15,	0,	"lo"},
*/
/*
	{"ping",		0,		AGENT_PING, 		0,	0},
*/	
/* New naming  */
/*
	{"system.cpu.idle1",	0,	SYSTEM_CPU_IDLE1, 	0,	0},
	{"system.cpu.idle5",	0,	SYSTEM_CPU_IDLE5, 	0,	0},
	{"system.cpu.idle15",	0,	SYSTEM_CPU_IDLE15, 	0,	0},
	{"system.cpu.nice1",	0,	SYSTEM_CPU_NICE1, 	0,	0},
	{"system.cpu.nice5",	0,	SYSTEM_CPU_NICE5, 	0,	0},
	{"system.cpu.nice15",	0,	SYSTEM_CPU_NICE15, 	0,	0},
	{"system.cpu.sys1",	0,	SYSTEM_CPU_SYS1, 	0,	0},
	{"system.cpu.sys5",	0,	SYSTEM_CPU_SYS5, 	0,	0},
	{"system.cpu.sys15",	0,	SYSTEM_CPU_SYS15, 	0,	0},
	{"system.cpu.user1",	0,	SYSTEM_CPU_USER1, 	0,	0},
	{"system.cpu.user5",	0,	SYSTEM_CPU_USER5, 	0,	0},
	{"system.cpu.user15",	0,	SYSTEM_CPU_USER15, 	0,	0},
*/
/*
	{"vm.memory.total",	0,	VM_MEMORY_TOTAL,	0,	0},
	{"vm.memory.shared",	0,	VM_MEMORY_SHARED,	0,	0},
	{"vm.memory.buffers",	0,	VM_MEMORY_BUFFERS,	0,	0},
	{"vm.memory.cached",	0,	VM_MEMORY_CACHED, 	0,	0},
	{"vm.memory.free",	0,	VM_MEMORY_FREE, 	0,	0},
*/
/*
	{"vfs.fs.free",		CF_USEUPARAM,	VFS_FS_FREE,		0,	"/"},
	{"vfs.fs.total",	CF_USEUPARAM,	VFS_FS_TOTAL,		0,	"/"},
	{"vfs.fs.used",		CF_USEUPARAM,	VFS_FS_USED,		0,	"/"},
	{"vfs.fs.pfree",	CF_USEUPARAM,	VFS_FS_PFREE,		0,	"/"},
	{"vfs.fs.pused",	CF_USEUPARAM,	VFS_FS_PUSED,		0,	"/"},
*/
/*
	{"vfs.fs.inode.free",	CF_USEUPARAM,	VFS_FS_INODE_FREE,	0,	"/"},
	{"vfs.fs.inode.total",	CF_USEUPARAM,	VFS_FS_INODE_TOTAL,	0,	"/"},
	{"vfs.fs.inode.pfree",	CF_USEUPARAM,	VFS_FS_INODE_PFREE,	0,	"/"},
*/
/*
	{"net.if.ibytes1",	CF_USEUPARAM,	NET_IF_IBYTES1,	0,	"lo"},
	{"net.if.ibytes5",	CF_USEUPARAM,	NET_IF_IBYTES5,	0,	"lo"},
	{"net.if.ibytes15",	CF_USEUPARAM,	NET_IF_IBYTES15,0,	"lo"},

	{"net.if.obytes1",	CF_USEUPARAM,	NET_IF_OBYTES1,	0,	"lo"},
	{"net.if.obytes5",	CF_USEUPARAM,	NET_IF_OBYTES5,	0,	"lo"},
	{"net.if.obytes15",	CF_USEUPARAM,	NET_IF_OBYTES15,0,	"lo"},
*/
/*
	{"disk_read_ops1",	CF_USEUPARAM,	DISKREADOPS1, 	0,	"hda"},
	{"disk_read_ops5",	CF_USEUPARAM,	DISKREADOPS5, 	0,	"hda"},
	{"disk_read_ops15",	CF_USEUPARAM,	DISKREADOPS15,	0,	"hda"},

	{"disk_read_blks1",	CF_USEUPARAM,	DISKREADBLKS1,	0,	"hda"},
	{"disk_read_blks5",	CF_USEUPARAM,	DISKREADBLKS5,	0,	"hda"},
	{"disk_read_blks15",	CF_USEUPARAM,	DISKREADBLKS15,	0,	"hda"},

	{"disk_write_ops1",	CF_USEUPARAM,	DISKWRITEOPS1, 	0,	"hda"},
	{"disk_write_ops5",	CF_USEUPARAM,	DISKWRITEOPS5, 	0,	"hda"},
	{"disk_write_ops15",	CF_USEUPARAM,	DISKWRITEOPS15,	0,	"hda"},

	{"disk_write_blks1",	CF_USEUPARAM,	DISKWRITEBLKS1,	0,	"hda"},
	{"disk_write_blks5",	CF_USEUPARAM,	DISKWRITEBLKS5,	0,	"hda"},
	{"disk_write_blks15",	CF_USEUPARAM,	DISKWRITEBLKS15,0,	"hda"},
*/
/*
	{"system.cpu.load1",	0,	SYSTEM_CPU_LOAD1,	0,	0},
	{"system.cpu.load5",	0,	SYSTEM_CPU_LOAD5,	0,	0},
	{"system.cpu.load15",	0,	SYSTEM_CPU_LOAD15,	0,	0},
*/

/****************************************
  	All these perameters require more than 1 second to retrieve.

  	{"swap[in]"		,EXECUTE, 0, "vmstat -n 1 2|tail -1|cut -b37-40"},
	{"swap[out]"		,EXECUTE, 0, "vmstat -n 1 2|tail -1|cut -b41-44"},

	{"system[interrupts]"	,EXECUTE, 0, "vmstat -n 1 2|tail -1|cut -b57-61"},
	{"system[switches]"	,EXECUTE, 0, "vmstat -n 1 2|tail -1|cut -b62-67"},
***************************************/

/*	{"tcp_count"		,EXECUTE, 	0, "netstat -tn|grep EST|wc -l"}, */
/*
	{"check_port",		CF_USEUPARAM,	CHECK_PORT,		0,	"80"},
	{"check_service",	CF_USEUPARAM,	CHECK_SERVICE, 		0,	"ssh,127.0.0.1,22"},
	{"check_service_perf", 	CF_USEUPARAM,	CHECK_SERVICE_PERF, 	0,	"ssh,127.0.0.1,22"},
*/
/*
	{"dns",			CF_USEUPARAM,	CHECK_DNS,		0,	"127.0.0.1,localhost"},
*/
	{"agent.ping",		0,	AGENT_PING, 		0,	0},
	{"agent.version",	0,	AGENT_VERSION,		0,	0},

	{"kernel.maxfiles",	0,	KERNEL_MAXFILES,	0,	0},
	{"kernel.maxproc",	0,	KERNEL_MAXPROC, 	0,	0},

	{"vfs.file.cksum",	CF_USEUPARAM,	VFS_FILE_CKSUM,		0,	"/etc/services"},
	{"vfs.file.md5sum",	CF_USEUPARAM,	VFS_FILE_MD5SUM,	0,	"/etc/services"},

/************************************
 *          NEW FUNCTIONS           *
 ************************************/

	{"system.cpu.switches", 0,              SYSTEM_CPU_SWITCHES,    0,      0},
	{"system.cpu.intr",     0,              SYSTEM_CPU_INTR,        0,      0},

	{"net.tcp.dns",		CF_USEUPARAM,	CHECK_DNS,		0,	"127.0.0.1,localhost"},

	{"net.tcp.listen",      CF_USEUPARAM,   NET_TCP_LISTEN, 0,      "80"},	

	{"net.tcp.port",	CF_USEUPARAM,	CHECK_PORT,		0,	",80"},
	{"net.tcp.service",	CF_USEUPARAM,	CHECK_SERVICE, 		0,	"ssh,127.0.0.1,22"},
	{"net.tcp.service.perf",CF_USEUPARAM,	CHECK_SERVICE_PERF, 	0,	"ssh,127.0.0.1,22"},

	{"net.if.in",		CF_USEUPARAM,	NET_IF_IN,	0,	"lo,bytes"},
	{"net.if.out",		CF_USEUPARAM,	NET_IF_OUT,	0,	"lo,bytes"},
        {"net.if.collisions",   CF_USEUPARAM,   NET_IF_COLLISIONS,      0,      "lo"},

	{"vfs.fs.size",		CF_USEUPARAM,	VFS_FS_SIZE,	0,	"/,free"},
	{"vfs.fs.inode",	CF_USEUPARAM,	VFS_FS_INODE,	0,	"/,free"},

	{"vfs.dev.read",	CF_USEUPARAM,	VFS_DEV_READ,	0,	"hda,bytes"},
	{"vfs.dev.write",	CF_USEUPARAM,	VFS_DEV_WRITE,	0,	"hda,operations"},

	{"vm.memory.size",	CF_USEUPARAM,	VM_MEMORY_SIZE,	0,	"free"},

	{"proc.num",		CF_USEUPARAM,	PROC_NUM,	0,	"inetd,,"},
	{"proc.mem",		CF_USEUPARAM,	PROC_MEMORY,	0,	"inetd,,"},

	{"system.cpu.util",	CF_USEUPARAM,	SYSTEM_CPU_UTIL,	0,	"all,idle"},

	{"system.cpu.load",	CF_USEUPARAM,	SYSTEM_CPU_LOAD,	0,	"all,avg1"},

	{"system.swap.size",	CF_USEUPARAM,	SYSTEM_SWAP_SIZE,	0,	"all,free"},
	{"system.swap.in",      CF_USEUPARAM,   SYSTEM_SWAP_IN,         0,      "all,pages"},
	{"system.swap.out",     CF_USEUPARAM,   SYSTEM_SWAP_OUT,        0,      "all,count"},	

	{"system.hostname",	0,	SYSTEM_HOSTNAME,	0,	0},

	{"system.uname",	0,	SYSTEM_UNAME,		0,	0},
	{"system.uptime",	0,	SYSTEM_UPTIME,		0,	0},
	{"system.users.num",	0,	SYSTEM_UNUM, 		0,	0},

	{0}
	};
