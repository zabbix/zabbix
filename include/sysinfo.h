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

extern int	CONFIG_ENABLE_REMOTE_COMMANDS;

/* #define TEST_PARAMETERS */

#define	SYSINFO_RET_OK		0
#define	SYSINFO_RET_FAIL	1
#define	SYSINFO_RET_TIMEOUT	2

typedef struct zbx_metric_type
{
	char		*key;
	unsigned	flags;
	int		(*function)();
	char		*main_param;
	char		*test_param;
} ZBX_METRIC;


/* flags for command */
#define CF_USEUPARAM	1	/* use user param */

/* flags for process */

#define PROCESS_TEST		1
#define PROCESS_USE_TEST_PARAM	2

int	process(const char *in_command, unsigned flags, AGENT_RESULT *result);
void	init_metrics();

void    add_user_parameter(char *key,char *command);
void	test_parameters(void);
void	test_parameter(char* key);

int     check_ntp(char *host, int port, int *value_int);

int     get_stat(const char *key, unsigned flags, AGENT_RESULT *result);

#ifdef  HAVE_PROC
int     getPROC(char *file, int lineno, int fieldno, unsigned flags, AGENT_RESULT *result);
#endif

/* external system functions */

int     OLD_CPU(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     OLD_IO(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     OLD_KERNEL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
#define	OLD_MEMORY VM_MEMORY_SIZE
int     OLD_SYSTEM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     OLD_SWAP(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     OLD_SENSOR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     OLD_VERSION(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);

int     AGENT_PING(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     AGENT_VERSION(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     KERNEL_MAXFILES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     KERNEL_MAXPROC(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     PROC_MEMORY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     PROC_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     NET_IF_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     NET_IF_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     NET_IF_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     SYSTEM_CPU_LOAD(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     SYSTEM_CPU_UTIL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     SYSTEM_HOSTNAME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     SYSTEM_SWAP_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     SYSTEM_SWAP_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     SYSTEM_SWAP_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     SYSTEM_UNAME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     SYSTEM_UNUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     SYSTEM_UPTIME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     VFS_DEV_READ(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     VFS_DEV_WRITE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     VFS_FILE_CKSUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     VFS_FILE_EXISTS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     VFS_FILE_MD5SUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     VFS_FILE_REGEXP(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     VFS_FILE_REGMATCH(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     VFS_FILE_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     VFS_FILE_TIME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     VFS_FS_INODE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     VFS_FS_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     VM_MEMORY_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);

int     NET_IF_COLLISIONS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_CPU_SWITCHES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_CPU_INTR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	TCP_LISTEN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	NET_TCP_LISTEN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	CHECK_SERVICE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	CHECK_SERVICE_PERF(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	CHECK_PORT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	CHECK_DNS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	RUN_COMMAND(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);

/* internal system functions */
int	EXECUTE_INT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	EXECUTE_STR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);

int	WEB_PAGE_GET(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	WEB_PAGE_PERF(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	WEB_PAGE_REGEXP(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);

#endif
