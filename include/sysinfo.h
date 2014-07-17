/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#ifndef ZABBIX_SYSINFO_H
#define ZABBIX_SYSINFO_H

#include "common.h"
#include "module.h"

/* CHECK RESULT */

#define ISSET_UI64(res)	((res)->type & AR_UINT64)
#define ISSET_DBL(res)	((res)->type & AR_DOUBLE)
#define ISSET_STR(res)	((res)->type & AR_STRING)
#define ISSET_TEXT(res)	((res)->type & AR_TEXT)
#define ISSET_LOG(res)	((res)->type & AR_LOG)
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

#define UNSET_LOG_RESULT(res)			\
						\
do						\
{						\
	if ((res)->type & AR_LOG)		\
	{					\
		zbx_logs_free((res)->logs);	\
		(res)->type &= ~AR_LOG;		\
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
	if (!(exc_type & AR_LOG))	UNSET_LOG_RESULT(res);	\
	if (!(exc_type & AR_MESSAGE))	UNSET_MSG_RESULT(res);	\
}								\
while (0)

/* RETRIEVE RESULT VALUE */

#define GET_UI64_RESULT(res)	((zbx_uint64_t *)get_result_value_by_type(res, AR_UINT64))
#define GET_DBL_RESULT(res)	((double *)get_result_value_by_type(res, AR_DOUBLE))
#define GET_STR_RESULT(res)	((char **)get_result_value_by_type(res, AR_STRING))
#define GET_TEXT_RESULT(res)	((char **)get_result_value_by_type(res, AR_TEXT))
#define GET_LOG_RESULT(res)	((zbx_log_t **)get_result_value_by_type(res, AR_LOG))
#define GET_MSG_RESULT(res)	((char **)get_result_value_by_type(res, AR_MESSAGE))

void    *get_result_value_by_type(AGENT_RESULT *result, int require_type);

extern int	CONFIG_ENABLE_REMOTE_COMMANDS;
extern int	CONFIG_LOG_REMOTE_COMMANDS;
extern int	CONFIG_UNSAFE_USER_PARAMETERS;

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

#define ZBX_DO_SUM		0
#define ZBX_DO_MAX		1
#define ZBX_DO_MIN		2
#define ZBX_DO_AVG		3
#define ZBX_DO_ONE		4

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

/* flags for process */
#define PROCESS_LOCAL_COMMAND	1
#define PROCESS_MODULE_COMMAND	2

void	init_metrics();
int	add_metric(ZBX_METRIC *metric, char *error, size_t max_error_len);
void	free_metrics();

int	process(const char *in_command, unsigned flags, AGENT_RESULT *result);

int	add_user_parameter(const char *key, char *command, char *error, size_t max_error_len);
int	add_user_module(const char *key, int (*function)());
void	test_parameters();
void	test_parameter(const char *key);

void	init_result(AGENT_RESULT *result);
void	zbx_logs_free(zbx_log_t **logs);
void	free_result(AGENT_RESULT *result);

void	init_request(AGENT_REQUEST *request);
void	free_request(AGENT_REQUEST *request);

int	parse_item_key(const char *itemkey, AGENT_REQUEST *request);

zbx_log_t	*add_log_result(AGENT_RESULT *result, const char *value);
void		set_log_result_empty(AGENT_RESULT *result);
zbx_uint64_t	get_log_result_lastlogsize(AGENT_RESULT *result);

void	unquote_key_param(char *param);
void	quote_key_param(char **param, int forced);

int	set_result_type(AGENT_RESULT *result, int value_type, int data_type, char *c);

#ifdef HAVE_KSTAT_H
zbx_uint64_t	get_kstat_numeric_value(const kstat_named_t *kn);
#endif

/* external system functions */

int	GET_SENSOR(AGENT_REQUEST *request, AGENT_RESULT *result);
int	KERNEL_MAXFILES(AGENT_REQUEST *request, AGENT_RESULT *result);
int	KERNEL_MAXPROC(AGENT_REQUEST *request, AGENT_RESULT *result);
int	PROC_MEM(AGENT_REQUEST *request, AGENT_RESULT *result);
int	PROC_NUM(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_IF_IN(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_IF_OUT(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_IF_TOTAL(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_IF_COLLISIONS(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_IF_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_TCP_LISTEN(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_UDP_LISTEN(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_CPU_SWITCHES(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_CPU_INTR(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_CPU_LOAD(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_CPU_UTIL(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_CPU_NUM(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_CPU_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_HOSTNAME(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_HW_CHASSIS(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_HW_CPU(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_HW_DEVICES(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_HW_MACADDR(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_SW_ARCH(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_SW_OS(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_SW_PACKAGES(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_SWAP_IN(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_SWAP_OUT(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_SWAP_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_UPTIME(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_UNAME(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SYSTEM_BOOTTIME(AGENT_REQUEST *request, AGENT_RESULT *result);
int	VFS_DEV_READ(AGENT_REQUEST *request, AGENT_RESULT *result);
int	VFS_DEV_WRITE(AGENT_REQUEST *request, AGENT_RESULT *result);
int	VFS_FS_INODE(AGENT_REQUEST *request, AGENT_RESULT *result);
int	VFS_FS_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result);
int	VFS_FS_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result);
int	VM_MEMORY_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result);

#ifdef _WINDOWS
int	USER_PERF_COUNTER(AGENT_REQUEST *request, AGENT_RESULT *result);
int	PERF_COUNTER(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SERVICE_STATE(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SERVICES(AGENT_REQUEST *request, AGENT_RESULT *result);
int	PROC_INFO(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_IF_LIST(AGENT_REQUEST *request, AGENT_RESULT *result);
int	WMI_GET(AGENT_REQUEST *request, AGENT_RESULT *result);
#endif

#ifdef _AIX
int	SYSTEM_STAT(AGENT_REQUEST *request, AGENT_RESULT *result);
#endif

typedef struct
{
	const char	*mode;
	int		(*function)();
}
MODE_FUNCTION;

#endif
