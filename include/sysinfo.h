/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
#define ISSET_META(res)	((res)->type & AR_META)

#define ISSET_VALUE(res)	((res)->type & (AR_UINT64 | AR_DOUBLE | AR_STRING | AR_TEXT | AR_LOG))

/* UNSET RESULT */

#define UNSET_UI64_RESULT(res)						\
									\
do									\
{									\
	(res)->type &= ~AR_UINT64;					\
	(res)->ui64 = (zbx_uint64_t)0;					\
}									\
while (0)

#define UNSET_DBL_RESULT(res)						\
									\
do									\
{									\
	(res)->type &= ~AR_DOUBLE;					\
	(res)->dbl = (double)0;						\
}									\
while (0)

#define UNSET_STR_RESULT(res)						\
									\
do									\
{									\
	if ((res)->type & AR_STRING)					\
	{								\
		zbx_free((res)->str);					\
		(res)->type &= ~AR_STRING;				\
	}								\
}									\
while (0)

#define UNSET_TEXT_RESULT(res)						\
									\
do									\
{									\
	if ((res)->type & AR_TEXT)					\
	{								\
		zbx_free((res)->text);					\
		(res)->type &= ~AR_TEXT;				\
	}								\
}									\
while (0)

#define UNSET_LOG_RESULT(res)						\
									\
do									\
{									\
	if ((res)->type & AR_LOG)					\
	{								\
		zbx_log_free((res)->log);				\
		(res)->log = NULL;					\
		(res)->type &= ~AR_LOG;					\
	}								\
}									\
while (0)

#define UNSET_MSG_RESULT(res)						\
									\
do									\
{									\
	if ((res)->type & AR_MESSAGE)					\
	{								\
		zbx_free((res)->msg);					\
		(res)->type &= ~AR_MESSAGE;				\
	}								\
}									\
while (0)

/* AR_META is always excluded */
#define UNSET_RESULT_EXCLUDING(res, exc_type) 					\
										\
do										\
{										\
	if (!(exc_type & AR_UINT64))	UNSET_UI64_RESULT(res);			\
	if (!(exc_type & AR_DOUBLE))	UNSET_DBL_RESULT(res);			\
	if (!(exc_type & AR_STRING))	UNSET_STR_RESULT(res);			\
	if (!(exc_type & AR_TEXT))	UNSET_TEXT_RESULT(res);			\
	if (!(exc_type & AR_LOG))	UNSET_LOG_RESULT(res);			\
	if (!(exc_type & AR_MESSAGE))	UNSET_MSG_RESULT(res);			\
}										\
while (0)

/* RETRIEVE RESULT VALUE */

#define GET_UI64_RESULT(res)	((zbx_uint64_t *)get_result_value_by_type(res, AR_UINT64))
#define GET_DBL_RESULT(res)	((double *)get_result_value_by_type(res, AR_DOUBLE))
#define GET_STR_RESULT(res)	((char **)get_result_value_by_type(res, AR_STRING))
#define GET_TEXT_RESULT(res)	((char **)get_result_value_by_type(res, AR_TEXT))
#define GET_LOG_RESULT(res)	((zbx_log_t *)get_result_value_by_type(res, AR_LOG))
#define GET_MSG_RESULT(res)	((char **)get_result_value_by_type(res, AR_MESSAGE))

void	*get_result_value_by_type(AGENT_RESULT *result, int require_type);

#define ZBX_FLOAT_PRECISION	0.0001

extern int	CONFIG_ENABLE_REMOTE_COMMANDS;
extern int	CONFIG_LOG_REMOTE_COMMANDS;
extern int	CONFIG_UNSAFE_USER_PARAMETERS;

/* collector */
#define MAX_COLLECTOR_HISTORY	(15 * SEC_PER_MIN + 1)
#define ZBX_AVG1		0
#define ZBX_AVG5		1
#define ZBX_AVG15		2
#define ZBX_AVG_COUNT		3

#if defined(_WINDOWS)
#	define MAX_COLLECTOR_PERIOD	(15 * SEC_PER_MIN)
#endif

#define ZBX_CPU_STATE_USER	0
#define ZBX_CPU_STATE_SYSTEM	1
#define ZBX_CPU_STATE_NICE	2
#define ZBX_CPU_STATE_IDLE	3
#define ZBX_CPU_STATE_INTERRUPT	4
#define ZBX_CPU_STATE_IOWAIT	5
#define ZBX_CPU_STATE_SOFTIRQ	6
#define ZBX_CPU_STATE_STEAL	7
#define ZBX_CPU_STATE_GCPU	8
#define ZBX_CPU_STATE_GNICE	9
#define ZBX_CPU_STATE_COUNT	10

#define ZBX_PROC_STAT_ALL	0
#define ZBX_PROC_STAT_RUN	1
#define ZBX_PROC_STAT_SLEEP	2
#define ZBX_PROC_STAT_ZOMB	3
#define ZBX_PROC_STAT_DISK	4
#define ZBX_PROC_STAT_TRACE	5

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
#define PROCESS_LOCAL_COMMAND	0x1
#define PROCESS_MODULE_COMMAND	0x2
#define PROCESS_WITH_ALIAS	0x4

typedef enum
{
	ZBX_KEY_ACCESS_ALLOW,
	ZBX_KEY_ACCESS_DENY
}
zbx_key_access_rule_type_t;

void	init_metrics(void);
int	add_metric(ZBX_METRIC *metric, char *error, size_t max_error_len);
int	add_metric_local(ZBX_METRIC *metric, char *error, size_t max_error_len);
void	free_metrics_ext(ZBX_METRIC **metrics);
void	free_metrics(void);

void	init_key_access_rules(void);
void	finalize_key_access_rules_configuration(void);
int	add_key_access_rule(const char *parameter, char *pattern, zbx_key_access_rule_type_t type);
int	check_key_access_rules(const char *metric);
int	check_request_access_rules(AGENT_REQUEST *request);
void	free_key_access_rules(void);

int	process(const char *in_command, unsigned flags, AGENT_RESULT *result);

void	set_user_parameter_dir(const char *path);
int	add_user_parameter(const char *itemkey, char *command, char *error, size_t max_error_len);
void	remove_user_parameters(void);
void	get_metrics_copy(ZBX_METRIC **metrics);
void	set_metrics(ZBX_METRIC *metrics);
int	add_user_module(const char *key, int (*function)(void));
void	test_parameters(void);
void	test_parameter(const char *key);

void	init_result(AGENT_RESULT *result);
void	zbx_log_free(zbx_log_t *log);
void	free_result(AGENT_RESULT *result);

void	init_request(AGENT_REQUEST *request);
void	free_request(AGENT_REQUEST *request);

int	parse_item_key(const char *itemkey, AGENT_REQUEST *request);

void	unquote_key_param(char *param);
int	quote_key_param(char **param, int forced);

int	set_result_type(AGENT_RESULT *result, int value_type, char *c);
void	set_result_meta(AGENT_RESULT *result, zbx_uint64_t lastlogsize, int mtime);

#ifdef HAVE_KSTAT_H
zbx_uint64_t	get_kstat_numeric_value(const kstat_named_t *kn);
#endif

/* external system functions */

int	GET_SENSOR(AGENT_REQUEST *request, AGENT_RESULT *result);
int	KERNEL_MAXFILES(AGENT_REQUEST *request, AGENT_RESULT *result);
int	KERNEL_MAXPROC(AGENT_REQUEST *request, AGENT_RESULT *result);
int	KERNEL_OPENFILES(AGENT_REQUEST *request, AGENT_RESULT *result);

#ifdef ZBX_PROCSTAT_COLLECTOR
int	PROC_CPU_UTIL(AGENT_REQUEST *request, AGENT_RESULT *result);
#endif

int	PROC_MEM(AGENT_REQUEST *request, AGENT_RESULT *result);
int	PROC_NUM(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_IF_IN(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_IF_OUT(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_IF_TOTAL(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_IF_COLLISIONS(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_IF_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_TCP_LISTEN(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_TCP_SOCKET_COUNT(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_UDP_LISTEN(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_UDP_SOCKET_COUNT(AGENT_REQUEST *request, AGENT_RESULT *result);
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
int	VFS_DEV_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result);
int	VFS_FS_INODE(AGENT_REQUEST *request, AGENT_RESULT *result);
int	VFS_FS_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result);
int	VFS_FS_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result);
int	VFS_FS_GET(AGENT_REQUEST *request, AGENT_RESULT *result);
int	VM_MEMORY_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result);

#if defined(_WINDOWS) || defined(__MINGW32__)
int	USER_PERF_COUNTER(AGENT_REQUEST *request, AGENT_RESULT *result);
int	PERF_COUNTER(AGENT_REQUEST *request, AGENT_RESULT *result);
int	PERF_COUNTER_EN(AGENT_REQUEST *request, AGENT_RESULT *result);
int	PERF_INSTANCE_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result);
int	PERF_INSTANCE_DISCOVERY_EN(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SERVICE_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SERVICE_INFO(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SERVICE_STATE(AGENT_REQUEST *request, AGENT_RESULT *result);
int	SERVICES(AGENT_REQUEST *request, AGENT_RESULT *result);
int	PROC_INFO(AGENT_REQUEST *request, AGENT_RESULT *result);
int	NET_IF_LIST(AGENT_REQUEST *request, AGENT_RESULT *result);
int	WMI_GET(AGENT_REQUEST *request, AGENT_RESULT *result);
int	WMI_GETALL(AGENT_REQUEST *request, AGENT_RESULT *result);
int	VM_VMEMORY_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result);
#endif

#ifdef _AIX
int	SYSTEM_STAT(AGENT_REQUEST *request, AGENT_RESULT *result);
#endif

#if defined(_WINDOWS) || defined(__MINGW32__)
typedef int (*zbx_metric_func_t)(AGENT_REQUEST *request, AGENT_RESULT *result, HANDLE timeout_event);
#else
typedef int (*zbx_metric_func_t)(AGENT_REQUEST *request, AGENT_RESULT *result);
#endif

typedef struct
{
	const char	*mode;
	int		(*function)(const char *devname, AGENT_RESULT *result);
}
MODE_FUNCTION;

typedef struct
{
	zbx_uint64_t	total;
	zbx_uint64_t	not_used;
	zbx_uint64_t	used;
	double		pfree;
	double		pused;
}
zbx_fs_metrics_t;

typedef struct
{
	char			fsname[MAX_STRING_LEN];
	char			fstype[MAX_STRING_LEN];
	zbx_fs_metrics_t	bytes;
	zbx_fs_metrics_t	inodes;
}
zbx_mpoint_t;

#define ZBX_LLD_MACRO_FSNAME		"{#FSNAME}"
#define ZBX_LLD_MACRO_FSTYPE		"{#FSTYPE}"
#define ZBX_LLD_MACRO_FSLABEL		"{#FSLABEL}"
#define ZBX_LLD_MACRO_FSDRIVETYPE	"{#FSDRIVETYPE}"

#define ZBX_SYSINFO_TAG_FSNAME			"fsname"
#define ZBX_SYSINFO_TAG_FSTYPE			"fstype"
#define ZBX_SYSINFO_TAG_FSLABEL			"fslabel"
#define ZBX_SYSINFO_TAG_FSDRIVETYPE		"fsdrivetype"
#define ZBX_SYSINFO_TAG_BYTES			"bytes"
#define ZBX_SYSINFO_TAG_INODES			"inodes"
#define ZBX_SYSINFO_TAG_TOTAL			"total"
#define ZBX_SYSINFO_TAG_FREE			"free"
#define ZBX_SYSINFO_TAG_USED			"used"
#define ZBX_SYSINFO_TAG_PFREE			"pfree"
#define ZBX_SYSINFO_TAG_PUSED			"pused"

#define ZBX_SYSINFO_FILE_TAG_TYPE		"type"
#define ZBX_SYSINFO_FILE_TAG_BASENAME		"basename"
#define ZBX_SYSINFO_FILE_TAG_PATHNAME		"pathname"
#define ZBX_SYSINFO_FILE_TAG_DIRNAME		"dirname"
#define ZBX_SYSINFO_FILE_TAG_USER		"user"
#define ZBX_SYSINFO_FILE_TAG_GROUP		"group"
#define ZBX_SYSINFO_FILE_TAG_PERMISSIONS	"permissions"
#define ZBX_SYSINFO_FILE_TAG_SID		"SID"
#define ZBX_SYSINFO_FILE_TAG_UID		"uid"
#define ZBX_SYSINFO_FILE_TAG_GID		"gid"
#define ZBX_SYSINFO_FILE_TAG_SIZE		"size"
#define ZBX_SYSINFO_FILE_TAG_TIME		"time"
#define ZBX_SYSINFO_FILE_TAG_TIMESTAMP		"timestamp"
#define ZBX_SYSINFO_FILE_TAG_TIME_ACCESS	"access"
#define ZBX_SYSINFO_FILE_TAG_TIME_MODIFY	"modify"
#define ZBX_SYSINFO_FILE_TAG_TIME_CHANGE	"change"

int	zbx_execute_threaded_metric(zbx_metric_func_t metric_func, AGENT_REQUEST *request, AGENT_RESULT *result);
void	zbx_mpoints_free(zbx_mpoint_t *mpoint);

/* the fields used by proc queries */
#define ZBX_SYSINFO_PROC_NONE		0x0000
#define ZBX_SYSINFO_PROC_PID		0x0001
#define ZBX_SYSINFO_PROC_NAME		0x0002
#define ZBX_SYSINFO_PROC_CMDLINE	0x0004
#define ZBX_SYSINFO_PROC_USER		0x0008

#if defined(_WINDOWS) || defined(__MINGW32__)
#define ZBX_MUTEX_ALL_ALLOW		0
#define ZBX_MUTEX_THREAD_DENIED		1
#define ZBX_MUTEX_LOGGING_DENIED	2
zbx_uint32_t get_thread_global_mutex_flag(void);
#endif

#ifndef _WINDOWS
int	hostname_handle_params(AGENT_REQUEST *request, AGENT_RESULT *result, char *hostname);
#endif

#endif

