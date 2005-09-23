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


#ifndef ZABBIX_SYSINFO_H
#define ZABBIX_SYSINFO_H

/* #define TEST_PARAMETERS */

#define	SYSINFO_RET_OK		0
#define	SYSINFO_RET_FAIL	1
#define	SYSINFO_RET_TIMEOUT	2

#define COMMAND struct command_type
COMMAND
{
	char	*key;
	int	(*function)();
        int	(*function_str)();
	char	*parameter;
};

int	process(char *command, char *value, int test);
void	init_metrics();

void    add_user_parameter(char *key,char *command);
void	test_parameters(void);
int	getPROC(char *file,int lineno,int fieldno, double *value);

int	VM_MEMORY_BUFFERS(const char *cmd, const char *parameter,double  *value);
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

int	SYSTEM_LOCALTIME(const char *cmd, const char *parameter,double  *value);
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

#endif
