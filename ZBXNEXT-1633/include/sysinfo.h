/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_SYSINFO_H
#define ZABBIX_SYSINFO_H

#include "common.h"

/* agent return value */
typedef struct
{
	int	 	type;
	zbx_uint64_t	ui64;
	double		dbl;
	char		*str;
	char		*text;
	char		*msg;
}
AGENT_RESULT;

/* agent result types */
#define AR_UINT64	0x01
#define AR_DOUBLE	0x02
#define AR_STRING	0x04
#define AR_TEXT		0x08
#define AR_MESSAGE	0x10

/* SET RESULT */

#define SET_UI64_RESULT(res, val)		\
(						\
	(res)->type |= AR_UINT64,		\
	(res)->ui64 = (zbx_uint64_t)(val)	\
)

#define SET_DBL_RESULT(res, val)		\
(						\
	(res)->type |= AR_DOUBLE,		\
	(res)->dbl = (double)(val)		\
)

/* NOTE: always allocate new memory for val! DON'T USE STATIC OR STACK MEMORY!!! */
#define SET_STR_RESULT(res, val)		\
(						\
	(res)->type |= AR_STRING,		\
	(res)->str = (char *)(val)		\
)

/* NOTE: always allocate new memory for val! DON'T USE STATIC OR STACK MEMORY!!! */
#define SET_TEXT_RESULT(res, val)		\
(						\
	(res)->type |= AR_TEXT,			\
	(res)->text = (char *)(val)		\
)

/* NOTE: always allocate new memory for val! DON'T USE STATIC OR STACK MEMORY!!! */
#define SET_MSG_RESULT(res, val)		\
(						\
	(res)->type |= AR_MESSAGE,		\
	(res)->msg = (char *)(val)		\
)

/* CHECK RESULT */

#define ISSET_UI64(res)	((res)->type & AR_UINT64)
#define ISSET_DBL(res)	((res)->type & AR_DOUBLE)
#define ISSET_STR(res)	((res)->type & AR_STRING)
#define ISSET_TEXT(res)	((res)->type & AR_TEXT)
#define ISSET_MSG(res)	((res)->type & AR_MESSAGE)

/* UNSET RESULT */

#define UNSET_UI64_RESULT(res)			\
(						\
	(res)->type &= ~AR_UINT64,		\
	(res)->ui64 = (zbx_uint64_t)0		\
)

#define UNSET_DBL_RESULT(res)			\
(						\
	(res)->type &= ~AR_DOUBLE,		\
	(res)->dbl = (double)0			\
)

#define UNSET_STR_RESULT(res)			\
						\
do						\
{						\
	if ((res)->type & AR_STRING)		\
	{					\
		zbx_free((res)->str);		\
		(res)->type &= ~AR_STRING;	\
	}					\
}						\
while (0)

#define UNSET_TEXT_RESULT(res)			\
						\
do						\
{						\
	if ((res)->type & AR_TEXT)		\
	{					\
		zbx_free((res)->text);		\
		(res)->type &= ~AR_TEXT;	\
	}					\
}						\
while (0)

#define UNSET_MSG_RESULT(res)			\
						\
do						\
{						\
	if ((res)->type & AR_MESSAGE)		\
	{					\
		zbx_free((res)->msg);		\
		(res)->type &= ~AR_MESSAGE;	\
	}					\
}						\
while (0)

#define UNSET_RESULT_EXCLUDING(res, exc_type) 			\
								\
do								\
{								\
	if (!(exc_type & AR_UINT64))	UNSET_UI64_RESULT(res);	\
	if (!(exc_type & AR_DOUBLE))	UNSET_DBL_RESULT(res);	\
	if (!(exc_type & AR_STRING))	UNSET_STR_RESULT(res);	\
	if (!(exc_type & AR_TEXT))	UNSET_TEXT_RESULT(res);	\
	if (!(exc_type & AR_MESSAGE))	UNSET_MSG_RESULT(res);	\
}								\
while (0)

/* RETRIEVE RESULT VALUE */

#define GET_UI64_RESULT(res)	((zbx_uint64_t *)get_result_value_by_type(res, AR_UINT64))
#define GET_DBL_RESULT(res)	((double *)get_result_value_by_type(res, AR_DOUBLE))
#define GET_STR_RESULT(res)	((char **)get_result_value_by_type(res, AR_STRING))
#define GET_TEXT_RESULT(res)	((char **)get_result_value_by_type(res, AR_TEXT))
#define GET_MSG_RESULT(res)	((char **)get_result_value_by_type(res, AR_MESSAGE))

void    *get_result_value_by_type(AGENT_RESULT *result, int require_type);

extern int	CONFIG_ENABLE_REMOTE_COMMANDS;
extern int	CONFIG_LOG_REMOTE_COMMANDS;
extern int	CONFIG_UNSAFE_USER_PARAMETERS;

typedef enum
{
	SYSINFO_RET_OK = 0,
	SYSINFO_RET_FAIL
}
ZBX_SYSINFO_RET;

typedef struct
{
	char		*key;
	unsigned	flags;
	int		(*function)();
	char		*main_param;
	char		*test_param;
}
ZBX_METRIC;

/* collector */
#define MAX_COLLECTOR_HISTORY	(15 * SEC_PER_MIN + 1)
#define ZBX_AVG1		0
#define ZBX_AVG5		1
#define ZBX_AVG15		2
#define ZBX_AVG_COUNT		3

#define ZBX_CPU_STATE_USER	0
#define ZBX_CPU_STATE_SYSTEM	1
#define ZBX_CPU_STATE_NICE	2
#define ZBX_CPU_STATE_IDLE	3
#define ZBX_CPU_STATE_INTERRUPT	4
#define ZBX_CPU_STATE_IOWAIT	5
#define ZBX_CPU_STATE_SOFTIRQ	6
#define ZBX_CPU_STATE_STEAL	7
#define ZBX_CPU_STATE_COUNT	8

#define ZBX_PROC_STAT_ALL	0
#define ZBX_PROC_STAT_RUN	1
#define ZBX_PROC_STAT_SLEEP	2
#define ZBX_PROC_STAT_ZOMB	3

#define ZBX_DSTAT_TYPE_SECT	0
#define ZBX_DSTAT_TYPE_OPER	1
#define ZBX_DSTAT_TYPE_BYTE	2
#define ZBX_DSTAT_TYPE_SPS	3
#define ZBX_DSTAT_TYPE_OPS	4
#define ZBX_DSTAT_TYPE_BPS	5

/* disk statistics */
#define ZBX_DSTAT_R_SECT	0
#define ZBX_DSTAT_R_OPER	1
#define ZBX_DSTAT_R_BYTE	2
#define ZBX_DSTAT_W_SECT	3
#define ZBX_DSTAT_W_OPER	4
#define ZBX_DSTAT_W_BYTE	5
#define ZBX_DSTAT_MAX		6
int	get_diskstat(const char *devname, zbx_uint64_t *dstat);

/* flags for command */
#define CF_USEUPARAM	1	/* use user param */

/* flags for process */
#define PROCESS_TEST		1
#define PROCESS_USE_TEST_PARAM	2
#define PROCESS_LOCAL_COMMAND	4

void	init_metrics();
void	free_metrics();

int	process(const char *in_command, unsigned flags, AGENT_RESULT *result);

int	add_user_parameter(const char *key, char *command);
void	test_parameters();
void	test_parameter(const char *key, unsigned flags);

void	init_result(AGENT_RESULT *result);
void	free_result(AGENT_RESULT *result);

int	set_result_type(AGENT_RESULT *result, int value_type, int data_type, char *c);

/* external system functions */

int	GET_SENSOR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	KERNEL_MAXFILES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	KERNEL_MAXPROC(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	PROC_MEM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	PROC_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	NET_IF_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	NET_IF_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	NET_IF_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	NET_IF_COLLISIONS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	NET_IF_DISCOVERY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	NET_TCP_LISTEN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	NET_UDP_LISTEN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_CPU_SWITCHES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_CPU_INTR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_CPU_LOAD(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_CPU_UTIL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_CPU_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_HW_CHASSIS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_HW_CPU(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_HW_DEVICES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_HW_MACADDR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_SW_ARCH(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_SW_OS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_SW_PACKAGES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_SWAP_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_SWAP_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_SWAP_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_UPTIME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_BOOTTIME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	VFS_DEV_READ(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	VFS_DEV_WRITE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	VFS_FS_INODE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	VFS_FS_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	VFS_FS_DISCOVERY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	VM_MEMORY_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);

#ifdef _WINDOWS
int	USER_PERF_COUNTER(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	PERF_COUNTER(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SERVICE_STATE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SERVICES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	PROC_INFO(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	NET_IF_LIST(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
#endif

#ifdef _AIX
int	SYSTEM_STAT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
#endif

typedef struct
{
	const char	*mode;
	int		(*function)();
}
MODE_FUNCTION;

#endif
