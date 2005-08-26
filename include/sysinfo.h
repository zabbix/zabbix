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

int	process(char *command, char *value);
void	init_metrics();

void    add_user_parameter(char *key,char *command);
void	test_parameters(void);
int	getPROC(char *file,int lineno,int fieldno, double *value);

int	BUFFERSMEM(const char *cmd, const char *parameter,double  *value);
int	CACHEDMEM(const char *cmd, const char *parameter,double  *value);
int	CKSUM(const char *cmd, const char *filename,double  *value);

int	CPUIDLE1(const char *cmd, const char *parameter,double  *value);
int	CPUIDLE5(const char *cmd, const char *parameter,double  *value);
int	CPUIDLE15(const char *cmd, const char *parameter,double  *value);
int	CPUUSER1(const char *cmd, const char *parameter,double  *value);
int	CPUUSER5(const char *cmd, const char *parameter,double  *value);
int	CPUUSER15(const char *cmd, const char *parameter,double  *value);
int	CPUNICE1(const char *cmd, const char *parameter,double  *value);
int	CPUNICE5(const char *cmd, const char *parameter,double  *value);
int	CPUNICE15(const char *cmd, const char *parameter,double  *value);
int	CPUSYSTEM1(const char *cmd, const char *parameter,double  *value);
int	CPUSYSTEM5(const char *cmd, const char *parameter,double  *value);
int	CPUSYSTEM15(const char *cmd, const char *parameter,double  *value);

int	DISKTOTAL(const char *cmd, const char *mountPoint,double  *value);
int	DISKFREE(const char *cmd, const char *mountPoint,double  *value);
int	DISKUSED(const char *cmd, const char *mountPoint,double  *value);
int	DISKFREE_PERC(const char *cmd, const char *mountPoint,double  *value);
int	DISKUSED_PERC(const char *cmd, const char *mountPoint,double  *value);

int	DISK_IO(const char *cmd, const char *parameter,double  *value);
int	DISK_RIO(const char *cmd, const char *parameter,double  *value);
int	DISK_WIO(const char *cmd, const char *parameter,double  *value);
int	DISK_RBLK(const char *cmd, const char *parameter,double  *value);
int	DISK_WBLK(const char *cmd, const char *parameter,double  *value);
int	FREEMEM(const char *cmd, const char *parameter,double  *value);

int	FS_FILE_ATIME(const char *cmd, const char *filename,double  *value);
int	FS_FILE_CTIME(const char *cmd, const char *filename,double  *value);
int	FS_FILE_MTIME(const char *cmd, const char *filename,double  *value);
int	FS_FILE_SIZE(const char *cmd, const char *filename,double  *value);
int	FS_FILE_EXISTS(const char *cmd, const char *filename,double  *value);

int	INODEFREE(const char *cmd, const char *mountPoint,double  *value);
int	INODEFREE_PERC(const char *cmd, const char *mountPoint,double  *value);
int	INODETOTAL(const char *cmd, const char *mountPoint,double  *value);


int	KERNEL_MAXFILES(const char *cmd, const char *parameter,double  *value);
int	KERNEL_MAXPROC(const char *cmd, const char *parameter,double  *value);
int	NETLOADIN1(const char *cmd, const char *parameter,double  *value);
int	NETLOADIN5(const char *cmd, const char *parameter,double  *value);
int	NETLOADIN15(const char *cmd, const char *parameter,double  *value);
int	NETLOADOUT1(const char *cmd, const char *parameter,double  *value);
int	NETLOADOUT5(const char *cmd, const char *parameter,double  *value);
int	NETLOADOUT15(const char *cmd, const char *parameter,double  *value);
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
int	PING(const char *cmd, const char *parameter,double  *value);
int	SHAREDMEM(const char *cmd, const char *parameter,double  *value);
int	TOTALMEM(const char *cmd, const char *parameter,double  *value);
int	PROCCNT(const char *cmd, const char *parameter,double  *value);
int	PROCCOUNT(const char *cmd, const char *parameter,double  *value);

int	PROCLOAD(const char *cmd, const char *parameter,double  *value);
int	PROCLOAD5(const char *cmd, const char *parameter,double  *value);
int	PROCLOAD15(const char *cmd, const char *parameter,double  *value);

int	SENSOR_TEMP1(const char *cmd, const char *parameter,double  *value);
int	SENSOR_TEMP2(const char *cmd, const char *parameter,double  *value);
int	SENSOR_TEMP3(const char *cmd, const char *parameter,double  *value);

int	SYSTEM_LOCALTIME(const char *cmd, const char *parameter,double  *value);

int	SWAPFREE(const char *cmd, const char *parameter,double  *value);
int	SWAPTOTAL(const char *cmd, const char *parameter,double  *value);
int	UPTIME(const char *cmd, const char *parameter,double  *value);

int	TCP_LISTEN(const char *cmd, const char *porthex,double  *value);

int	EXECUTE(const char *cmd, const char *command,double  *value);
int	EXECUTE_STR(const char *cmd, const char *command, const char *parameter, char  **value);
int	STRVERSION(const char *cmd, const char *command,char **value);

int	MD5SUM(const char *cmd, const char *filename, char **value);

int	CHECK_SERVICE(const char *cmd, const char *service,double  *value);
int	CHECK_SERVICE_PERF(const char *cmd, const char *service,double  *value);
int	CHECK_PORT(const char *cmd, const char *ip_and_port,double  *value);

#endif
