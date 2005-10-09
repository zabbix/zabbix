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
#include "sysinfo.h"

/*int	VM_MEMORY_BUFFERS(const char *cmd, const char *parameter,double  *value);*/
int	VM_MEMORY_CACHED(const char *cmd, const char *parameter,double  *value);

int	SYSTEM_CPU_IDLE1(const char *cmd, const char *parameter,double  *value);
int	SYSTEM_CPU_IDLE5(const char *cmd, const char *parameter,double  *value);
int	SYSTEM_CPU_IDLE15(const char *cmd, const char *parameter,double  *value);
int	SYSTEM_CPU_USER1(const char *cmd, const char *parameter,double  *value);
int	SYSTEM_CPU_USER5(const char *cmd, const char *parameter,double  *value);
int	SYSTEM_CPU_USER15(const char *cmd, const char *parameter,double  *value);
int	SYSTEM_CPU_NICE1(const char *cmd, const char *parameter,double  *value);
int	SYSTEM_CPU_NICE5(const char *cmd, const char *parameter,double  *value);
int	SYSTEM_CPU_NICE15(const char *cmd, const char *parameter,double  *value);
int	SYSTEM_CPU_SYS1(const char *cmd, const char *parameter,double  *value);
int	SYSTEM_CPU_SYS5(const char *cmd, const char *parameter,double  *value);
int	SYSTEM_CPU_SYS15(const char *cmd, const char *parameter,double  *value);

int	VFS_FS_TOTAL(const char *cmd, const char *mountPoint,double  *value);
int	VFS_FS_FREE(const char *cmd, const char *mountPoint,double  *value);
int	VFS_FS_USED(const char *cmd, const char *mountPoint,double  *value);
int	VFS_FS_PFREE(const char *cmd, const char *mountPoint,double  *value);
int	VFS_FS_PUSED(const char *cmd, const char *mountPoint,double  *value);

int	DISK_IO(const char *cmd, const char *parameter,double  *value);
int	DISK_RIO(const char *cmd, const char *parameter,double  *value);
int	DISK_WIO(const char *cmd, const char *parameter,double  *value);
int	DISK_RBLK(const char *cmd, const char *parameter,double  *value);
int	DISK_WBLK(const char *cmd, const char *parameter,double  *value);
int	VM_MEMORY_FREE(const char *cmd, const char *parameter,double  *value);

int	VFS_FILE_ATIME(const char *cmd, const char *filename,double  *value);
int	VFS_FILE_CKSUM(const char *cmd, const char *filename,double  *value);
int	VFS_FILE_CTIME(const char *cmd, const char *filename,double  *value);
int	VFS_FILE_MD5SUM(const char *cmd, const char *filename, char **value);
int	VFS_FILE_MTIME(const char *cmd, const char *filename,double  *value);
int	VFS_FILE_REGEXP(const char *cmd, const char *filename, char **value);
int	VFS_FILE_REGMATCH(const char *cmd, const char *filename,double  *value);
int	VFS_FILE_SIZE(const char *cmd, const char *filename,double  *value);
int	VFS_FILE_EXISTS(const char *cmd, const char *filename,double  *value);

int	VFS_FS_INODE_FREE(const char *cmd, const char *mountPoint,double  *value);
int	VFS_FS_INODE_PFREE(const char *cmd, const char *mountPoint,double  *value);
int	VFS_FS_INODE_TOTAL(const char *cmd, const char *mountPoint,double  *value);


int	KERNEL_MAXFILES(const char *cmd, const char *parameter,double  *value);
int	KERNEL_MAXPROC(const char *cmd, const char *parameter,double  *value);

int	NET_IF_IBYTES1(const char *cmd, const char *parameter,double  *value);
int	NET_IF_IBYTES5(const char *cmd, const char *parameter,double  *value);
int	NET_IF_IBYTES15(const char *cmd, const char *parameter,double  *value);

int	NET_IF_OBYTES1(const char *cmd, const char *parameter,double  *value);
int	NET_IF_OBYTES5(const char *cmd, const char *parameter,double  *value);
int	NET_IF_OBYTES15(const char *cmd, const char *parameter,double  *value);

int	DISKREADOPS1(const char *cmd, const char *parameter,double  *value);
int	DISKREADOPS5(const char *cmd, const char *parameter,double  *value);
int	DISKREADOPS15(const char *cmd, const char *parameter,double  *value);
int	DISKREADBLKS1(const char *cmd, const char *parameter,double  *value);
int	DISKREADBLKS5(const char *cmd, const char *parameter,double  *value);
int	DISKREADBLKS15(const char *cmd, const char *parameter,double  *value);
int	DISKWRITEOPS1(const char *cmd, const char *parameter,double  *value);
int	DISKWRITEOPS5(const char *cmd, const char *parameter,double  *value);
int	DISKWRITEOPS15(const char *cmd, const char *parameter,double  *value);
int	DISKWRITEBLKS1(const char *cmd, const char *parameter,double  *value);
int	DISKWRITEBLKS5(const char *cmd, const char *parameter,double  *value);
int	DISKWRITEBLKS15(const char *cmd, const char *parameter,double  *value);
int	AGENT_PING(const char *cmd, const char *parameter,double  *value);
int	VM_MEMORY_SHARED(const char *cmd, const char *parameter,double  *value);
int	VM_MEMORY_TOTAL(const char *cmd, const char *parameter,double  *value);
int	PROC_NUM(const char *cmd, const char *parameter,double  *value);
int	PROCCOUNT(const char *cmd, const char *parameter,double  *value);

int	SYSTEM_CPU_LOAD1(const char *cmd, const char *parameter,double  *value);
int	SYSTEM_CPU_LOAD5(const char *cmd, const char *parameter,double  *value);
int	SYSTEM_CPU_LOAD15(const char *cmd, const char *parameter,double  *value);

int	SENSOR_TEMP1(const char *cmd, const char *parameter,double  *value);
int	SENSOR_TEMP2(const char *cmd, const char *parameter,double  *value);
int	SENSOR_TEMP3(const char *cmd, const char *parameter,double  *value);

int	SYSTEM_UPTIME(const char *cmd, const char *parameter,double  *value);

int	SYSTEM_SWAP_FREE(const char *cmd, const char *parameter,double  *value);
int	SYSTEM_SWAP_TOTAL(const char *cmd, const char *parameter,double  *value);

int	TCP_LISTEN(const char *cmd, const char *porthex,double  *value);

int	EXECUTE(const char *cmd, const char *command,double  *value);
int	EXECUTE_STR(const char *cmd, const char *command, const char *parameter, char  **value);
int	AGENT_VERSION(const char *cmd, const char *command,char **value);


int	CHECK_SERVICE(const char *cmd, const char *service,double  *value);
int	CHECK_SERVICE_PERF(const char *cmd, const char *service,double  *value);
int	CHECK_PORT(const char *cmd, const char *ip_and_port,double  *value);
int	CHECK_DNS(const char *cmd, const char *service,double  *value);

COMMAND	parameters_specific[]=
/* 	KEY		FUNCTION (if double) FUNCTION (if string) PARAM*/
	{

/* Outdated */

	{"cksum[*]"		,VFS_FILE_CKSUM, 	0, "/etc/services"},
	{"cpu[idle1]"		,SYSTEM_CPU_IDLE1, 	0, 0},
	{"cpu[idle5]"		,SYSTEM_CPU_IDLE5, 	0, 0},
	{"cpu[idle15]"		,SYSTEM_CPU_IDLE15, 	0, 0},
	{"cpu[nice1]"		,SYSTEM_CPU_NICE1, 	0, 0},
	{"cpu[nice5]"		,SYSTEM_CPU_NICE5, 	0, 0},
	{"cpu[nice15]"		,SYSTEM_CPU_NICE15, 	0, 0},
	{"cpu[system1]"		,SYSTEM_CPU_SYS1, 	0, 0},
	{"cpu[system5]"		,SYSTEM_CPU_SYS5, 	0, 0},
	{"cpu[system15]"	,SYSTEM_CPU_SYS15, 	0, 0},
	{"cpu[user1]"		,SYSTEM_CPU_USER1, 	0, 0},
	{"cpu[user5]"		,SYSTEM_CPU_USER5, 	0, 0},
	{"cpu[user15]"		,SYSTEM_CPU_USER15, 	0, 0},
	{"diskfree[*]"		,VFS_FS_FREE,		0, "/"},
	{"disktotal[*]"		,VFS_FS_TOTAL,		0, "/"},
	{"diskused[*]"		,VFS_FS_USED,		0, "/"},
	{"diskfree_perc[*]"	,VFS_FS_PFREE,		0, "/"},
	{"diskused_perc[*]"	,VFS_FS_PUSED,		0, "/"},
	{"file[*]"		,VFS_FILE_EXISTS,	0, "/etc/passwd"},
	{"filesize[*]"		,VFS_FILE_SIZE, 		0, "/etc/passwd"},
	{"inodefree[*]"		,VFS_FS_INODE_FREE,	0, "/"},
	{"inodetotal[*]"	,VFS_FS_INODE_TOTAL,	0, "/"},
	{"inodefree_perc[*]"	,VFS_FS_INODE_PFREE,	0, "/"},
	{"kern[maxfiles]"	,KERNEL_MAXFILES,	0, 0},
	{"kern[maxproc]"	,KERNEL_MAXPROC, 	0, 0},
	{"md5sum[*]"		,0, 			VFS_FILE_MD5SUM, "/etc/services"},
	{"memory[buffers]"	,VM_MEMORY_BUFFERS,	0, 0},
	{"memory[cached]"	,VM_MEMORY_CACHED, 	0, 0},
	{"memory[free]"		,VM_MEMORY_FREE, 	0, 0},
	{"memory[shared]"	,VM_MEMORY_SHARED, 	0, 0},
	{"memory[total]"	,VM_MEMORY_TOTAL,	0, 0},
	{"netloadin1[*]"	,NET_IF_IBYTES1,	0, "lo"},
	{"netloadin5[*]"	,NET_IF_IBYTES5,	0, "lo"},
	{"netloadin15[*]"	,NET_IF_IBYTES15,	0, "lo"},
	{"netloadout1[*]"	,NET_IF_OBYTES1,	0, "lo"},
	{"netloadout5[*]"	,NET_IF_OBYTES5, 	0, "lo"},
	{"netloadout15[*]"	,NET_IF_OBYTES15,	0, "lo"},
	{"ping"			,AGENT_PING, 		0, 0},
	{"proc_cnt[*]"		,PROC_NUM, 		0, "inetd"},
	{"swap[free]"		,SYSTEM_SWAP_FREE,	0, 0},
	{"swap[total]"		,SYSTEM_SWAP_TOTAL,	0, 0},
	{"system[procload]"	,SYSTEM_CPU_LOAD1, 	0, 0},
	{"system[procload5]"	,SYSTEM_CPU_LOAD5, 	0, 0},
	{"system[procload15]"	,SYSTEM_CPU_LOAD15, 	0, 0},
	{"system[hostname]"	,0,		EXECUTE_STR, "hostname"},
	{"system[uname]"	,0,		EXECUTE_STR, "uname -a"},
	{"system[uptime]"	,SYSTEM_UPTIME,	0, 0},
	{"system[users]"	,EXECUTE, 	0,"who|wc -l"},
	{"version[zabbix_agent]",	0, 		AGENT_VERSION, 0},
/* New naming  */

	{"agent.ping"		,AGENT_PING, 		0, 0},
	{"agent.version",		0, 		AGENT_VERSION, 0},

	{"kernel.maxfiles]"	,KERNEL_MAXFILES,	0, 0},
	{"kernel.maxproc"	,KERNEL_MAXPROC, 	0, 0},

	{"proc.num[*]"		,PROC_NUM, 		0, "inetd"},

	{"vm.memory.total"	,VM_MEMORY_TOTAL,	0, 0},
	{"vm.memory.shared"	,VM_MEMORY_SHARED,	0, 0},
	{"vm.memory.buffers"	,VM_MEMORY_BUFFERS,	0, 0},
	{"vm.memory.cached"	,VM_MEMORY_CACHED, 	0, 0},
	{"vm.memory.free"	,VM_MEMORY_FREE, 	0, 0},

	{"vfs.fs.free[*]"	,VFS_FS_FREE,		0, "/"},
	{"vfs.fs.total[*]"	,VFS_FS_TOTAL,		0, "/"},
	{"vfs.fs.used[*]"	,VFS_FS_USED,		0, "/"},

	{"vfs.fs.pfree[*]"	,VFS_FS_PFREE,		0, "/"},
	{"vfs.fs.pused[*]"	,VFS_FS_PUSED,		0, "/"},

	{"vfs.fs.inode.free[*]"	,VFS_FS_INODE_FREE,	0, "/"},
	{"vfs.fs.inode.total[*]",VFS_FS_INODE_TOTAL,	0, "/"},
	{"vfs.fs.inode.pfree[*]",VFS_FS_INODE_PFREE,	0, "/"},

	{"vfs.file.atime[*]"	,VFS_FILE_ATIME,	0, "/etc/passwd"},
	{"vfs.file.cksum[*]"	,VFS_FILE_CKSUM,	0, "/etc/services"},
	{"vfs.file.ctime[*]"	,VFS_FILE_CTIME,	0, "/etc/passwd"},
	{"vfs.file.exists[*]"	,VFS_FILE_EXISTS,	0, "/etc/passwd"},
	{"vfs.file.md5sum[*]"	,0, 			VFS_FILE_MD5SUM, "/etc/services"},
	{"vfs.file.mtime[*]"	,VFS_FILE_MTIME,		0, "/etc/passwd"},
	{"vfs.file.regexp[*]"	,0, 			VFS_FILE_REGEXP, "/etc/passwd,root"},
	{"vfs.file.regmatch[*]"	,VFS_FILE_REGMATCH, 	0, "/etc/passwd,root"},
	{"vfs.file.size[*]"	,VFS_FILE_SIZE, 		0, "/etc/passwd"},

	{"system.cpu.idle1"	,SYSTEM_CPU_IDLE1, 		0, 0},
	{"system.cpu.idle5"	,SYSTEM_CPU_IDLE5, 		0, 0},
	{"system.cpu.idle15"	,SYSTEM_CPU_IDLE15, 		0, 0},
	{"system.cpu.nice1"	,SYSTEM_CPU_NICE1, 		0, 0},
	{"system.cpu.nice5"	,SYSTEM_CPU_NICE5, 		0, 0},
	{"system.cpu.nice15"	,SYSTEM_CPU_NICE15, 		0, 0},
	{"system.cpu.sys1"	,SYSTEM_CPU_SYS1, 		0, 0},
	{"system.cpu.sys5"	,SYSTEM_CPU_SYS5, 		0, 0},
	{"system.cpu.sys15"	,SYSTEM_CPU_SYS15, 		0, 0},
	{"system.cpu.user1"	,SYSTEM_CPU_USER1, 		0, 0},
	{"system.cpu.user5"	,SYSTEM_CPU_USER5, 		0, 0},
	{"system.cpu.user15"	,SYSTEM_CPU_USER15, 		0, 0},

	{"net.if.ibytes1[*]"	,NET_IF_IBYTES1,	0, "lo"},
	{"net.if.ibytes5[*]"	,NET_IF_IBYTES5,	0, "lo"},
	{"net.if.ibytes15[*]"	,NET_IF_IBYTES15,	0, "lo"},
	{"net.if.obytes1[*]"	,NET_IF_OBYTES1,	0, "lo"},
	{"net.if.obytes5[*]"	,NET_IF_OBYTES5,	0, "lo"},
	{"net.if.obytes15[*]"	,NET_IF_OBYTES15,	0, "lo"},

	{"disk_read_ops1[*]"	,DISKREADOPS1, 		0, "hda"},
	{"disk_read_ops5[*]"	,DISKREADOPS5, 		0, "hda"},
	{"disk_read_ops15[*]"	,DISKREADOPS15,		0, "hda"},

	{"disk_read_blks1[*]"	,DISKREADBLKS1,		0, "hda"},
	{"disk_read_blks5[*]"	,DISKREADBLKS5,		0, "hda"},
	{"disk_read_blks15[*]"	,DISKREADBLKS15,	0, "hda"},

	{"disk_write_ops1[*]"	,DISKWRITEOPS1, 	0, "hda"},
	{"disk_write_ops5[*]"	,DISKWRITEOPS5, 	0, "hda"},
	{"disk_write_ops15[*]"	,DISKWRITEOPS15,	0, "hda"},

	{"disk_write_blks1[*]"	,DISKWRITEBLKS1,	0, "hda"},
	{"disk_write_blks5[*]"	,DISKWRITEBLKS5,	0, "hda"},
	{"disk_write_blks15[*]"	,DISKWRITEBLKS15,	0, "hda"},

	{"sensor[temp1]"	,SENSOR_TEMP1, 		0, 0},
	{"sensor[temp2]"	,SENSOR_TEMP2, 		0, 0},
	{"sensor[temp3]"	,SENSOR_TEMP3, 		0, 0},

	{"system.cpu.load1"	,SYSTEM_CPU_LOAD1,	0, 0},
	{"system.cpu.load5"	,SYSTEM_CPU_LOAD5,	0, 0},
	{"system.cpu.load15"	,SYSTEM_CPU_LOAD15,	0, 0},

	{"system.hostname"	,0,			EXECUTE_STR, "hostname"},

	{"system.swap.free"	,SYSTEM_SWAP_FREE,	0, 0},
	{"system.swap.total"	,SYSTEM_SWAP_TOTAL, 	0, 0},

	{"system.uname"		,0,			EXECUTE_STR, "uname -a"},
	{"system.uptime"	,SYSTEM_UPTIME,		0, 0},
	{"system.users.num"	,EXECUTE, 		0,"who|wc -l"},

/****************************************
  	All these perameters require more than 1 second to retrieve.

  	{"swap[in]"		,EXECUTE, 0, "vmstat -n 1 2|tail -1|cut -b37-40"},
	{"swap[out]"		,EXECUTE, 0, "vmstat -n 1 2|tail -1|cut -b41-44"},

	{"system[interrupts]"	,EXECUTE, 0, "vmstat -n 1 2|tail -1|cut -b57-61"},
	{"system[switches]"	,EXECUTE, 0, "vmstat -n 1 2|tail -1|cut -b62-67"},
***************************************/

	{"io[disk_io]"		,DISK_IO,  	0, 0},
	{"io[disk_rio]"		,DISK_RIO, 	0, 0},
	{"io[disk_wio]"		,DISK_WIO, 	0, 0},
	{"io[disk_rblk]"	,DISK_RBLK, 	0, 0},
	{"io[disk_wblk]"	,DISK_WBLK, 	0, 0},



	{"system[proccount]"	,PROCCOUNT, 	0, 0},

#ifdef HAVE_PROC_LOADAVG
	{"system[procrunning]"	,EXECUTE, 	0, "cat /proc/loadavg|cut -f1 -d'/'|cut -f4 -d' '"},
#endif

/*	{"tcp_count"		,EXECUTE, 	0, "netstat -tn|grep EST|wc -l"}, */

	{"net[listen_23]"	,TCP_LISTEN, 	0, "0017"},
	{"net[listen_80]"	,TCP_LISTEN, 	0, "0050"},

	{"check_port[*]"	,CHECK_PORT, 	0, "80"},

	{"check_service[*]"	,CHECK_SERVICE, 	0, "ssh,127.0.0.1,22"},
	{"dns[*]"		,CHECK_DNS,		0, "127.0.0.1,localhost"},
	{"check_service_perf[*]",CHECK_SERVICE_PERF, 	0, "ssh,127.0.0.1,22"},

	{0}
	};
