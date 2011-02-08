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

#include "common.h"

/* agent return value */
typedef struct zbx_result_s {
	int	 	type;
	zbx_uint64_t	ui64;
	double		dbl;
	char		*str;
	char		*text;
	char		*msg;
} AGENT_RESULT;

/* agent result types */
#define AR_UINT64	1
#define AR_DOUBLE	2
#define AR_STRING	4
#define AR_MESSAGE	8
#define AR_TEXT		16


/* SET RESULT */

#define SET_DBL_RESULT(res, val) \
	{ \
	(res)->type |= AR_DOUBLE; \
	(res)->dbl = (double)(val); \
	}

#define SET_UI64_RESULT(res, val) \
	{ \
	(res)->type |= AR_UINT64; \
	(res)->ui64 = (zbx_uint64_t)(val); \
	}

/* NOTE: always allocate new memory for val! DON'T USE STATIC OR STACK MEMORY!!! */
#define SET_STR_RESULT(res, val) \
	{ \
	(res)->type |= AR_STRING; \
	(res)->str = (char*)(val); \
	}

/* NOTE: always allocate new memory for val! DON'T USE STATIC OR STACK MEMORY!!! */
#define SET_TEXT_RESULT(res, val) \
	{ \
	(res)->type |= AR_TEXT; \
	(res)->text = (char*)(val); \
	}

/* NOTE: always allocate new memory for val! DON'T USE STATIC OR STACK MEMORY!!! */
#define SET_MSG_RESULT(res, val) \
	{ \
	(res)->type |= AR_MESSAGE; \
	(res)->msg = (char*)(val); \
	}

/* CHECK RESULT */

#define ISSET_UI64(res)	((res)->type & AR_UINT64)
#define ISSET_DBL(res)	((res)->type & AR_DOUBLE)
#define ISSET_STR(res)	((res)->type & AR_STRING)
#define ISSET_TEXT(res)	((res)->type & AR_TEXT)
#define ISSET_MSG(res)	((res)->type & AR_MESSAGE)

/* UNSER RESULT */

#define UNSET_DBL_RESULT(res)           \
	{                               \
	(res)->type &= ~AR_DOUBLE;      \
	(res)->dbl = (double)(0);        \
	}

#define UNSET_UI64_RESULT(res)             \
	{                                  \
	(res)->type &= ~AR_UINT64;         \
	(res)->ui64 = (zbx_uint64_t)(0); \
	}

#define UNSET_STR_RESULT(res)                      \
	{                                          \
		if((res)->type & AR_STRING){       \
			zbx_free((res)->str);      \
			(res)->type &= ~AR_STRING; \
		}                                  \
	}

#define UNSET_TEXT_RESULT(res)                   \
	{                                        \
		if((res)->type & AR_TEXT){       \
			zbx_free((res)->text);   \
			(res)->type &= ~AR_TEXT; \
		}                                \
	}

#define UNSET_MSG_RESULT(res)                       \
	{                                           \
		if((res)->type & AR_MESSAGE){       \
			zbx_free((res)->msg);       \
			(res)->type &= ~AR_MESSAGE; \
		}                                   \
	}

#define UNSET_RESULT_EXCLUDING(res, exc_type) 				\
	{								\
		if(!(exc_type & AR_DOUBLE))	UNSET_DBL_RESULT(res)	\
		if(!(exc_type & AR_UINT64))	UNSET_UI64_RESULT(res)	\
		if(!(exc_type & AR_STRING))	UNSET_STR_RESULT(res)	\
		if(!(exc_type & AR_TEXT))	UNSET_TEXT_RESULT(res)	\
		if(!(exc_type & AR_MESSAGE))	UNSET_MSG_RESULT(res)	\
	}



/* RETRIVE RESULT VALUE */

#define GET_UI64_RESULT(res)	((zbx_uint64_t*)get_result_value_by_type(res, AR_UINT64))
#define GET_DBL_RESULT(res)	((double*)get_result_value_by_type(res, AR_DOUBLE))
#define GET_STR_RESULT(res)	((char**)get_result_value_by_type(res, AR_STRING))
#define GET_TEXT_RESULT(res)	((char**)get_result_value_by_type(res, AR_TEXT))
#define GET_MSG_RESULT(res)	((char**)get_result_value_by_type(res, AR_MESSAGE))

void    *get_result_value_by_type(AGENT_RESULT *result, int require_type);

extern int	CONFIG_ENABLE_REMOTE_COMMANDS;
extern int	CONFIG_LOG_REMOTE_COMMANDS;
extern int	CONFIG_UNSAFE_USER_PARAMETERS;

/* #define TEST_PARAMETERS */

typedef enum {
	SYSINFO_RET_OK		= 0,
	SYSINFO_RET_FAIL	= 1,
	SYSINFO_RET_TIMEOUT	= 2
} ZBX_SYSINFO_RET;

typedef struct zbx_metric_type
{
	char		*key;
	unsigned	flags;
	int		(*function)();
	char		*main_param;
	char		*test_param;
} ZBX_METRIC;

/* collector */
#define MAX_COLLECTOR_HISTORY 901 /* 15 min in seconds */
#define ZBX_AVG1	0
#define ZBX_AVG5	1
#define ZBX_AVG15	2
#define ZBX_AVGMAX	3

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

/* Disk statistics */
#define ZBX_DSTAT_R_SECT	0
#define ZBX_DSTAT_R_OPER	1
#define ZBX_DSTAT_R_BYTE	2
#define ZBX_DSTAT_W_SECT	3
#define ZBX_DSTAT_W_OPER	4
#define ZBX_DSTAT_W_BYTE	5
#define ZBX_DSTAT_MAX		6
void	refresh_diskdevices();
int	get_diskstat(const char *devname, zbx_uint64_t *dstat);

/* flags for command */
#define CF_USEUPARAM	1	/* use user param */

/* flags for process */

#define PROCESS_TEST		1
#define PROCESS_USE_TEST_PARAM	2

void	init_metrics(void);
void	free_metrics(void);

int	process(const char *in_command, unsigned flags, AGENT_RESULT *result);

int	add_user_parameter(char *key, char *command);
void	test_parameters(void);
void	test_parameter(const char* key, unsigned flags);

void   	init_result(AGENT_RESULT *result);
int    	copy_result(AGENT_RESULT *src, AGENT_RESULT *dist);
void   	free_result(AGENT_RESULT *result);

int	set_result_type(AGENT_RESULT *result, int value_type, int data_type, char *c);

/* external system functions */

int     KERNEL_MAXFILES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     KERNEL_MAXPROC(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     PROC_MEMORY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     PROC_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     NET_IF_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     NET_IF_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     NET_IF_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     SYSTEM_CPU_LOAD(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     SYSTEM_CPU_UTIL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     SYSTEM_SWAP_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     SYSTEM_SWAP_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     SYSTEM_SWAP_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     SYSTEM_UPTIME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     SYSTEM_BOOTTIME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     VFS_DEV_READ(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     VFS_DEV_WRITE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     VFS_FILE_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     VFS_FS_INODE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     VFS_FS_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int     VM_MEMORY_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);

int     NET_IF_COLLISIONS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_CPU_SWITCHES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_CPU_INTR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SYSTEM_CPU_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	NET_TCP_LISTEN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);

#if defined(_WINDOWS)
int	USER_PERFCOUNTER(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	PERF_MONITOR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SERVICE_STATE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	SERVICES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	PROC_INFO(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	NET_IF_LIST(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
#endif /* _WINDOWS */

#ifdef _AIX
int	SYSTEM_STAT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
#endif	/* _AIX */

#endif
