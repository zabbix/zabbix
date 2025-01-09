/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#ifndef ZABBIX_SYSINFO_H
#define ZABBIX_SYSINFO_H

#include "zbxalgo.h"

#define ZBX_PROC_STAT_ALL	0
#define ZBX_PROC_STAT_RUN	1
#define ZBX_PROC_STAT_SLEEP	2
#define ZBX_PROC_STAT_ZOMB	3
#define ZBX_PROC_STAT_DISK	4
#define ZBX_PROC_STAT_TRACE	5

#define ZBX_PROC_MODE_PROCESS	0
#define ZBX_PROC_MODE_THREAD	1
#define ZBX_PROC_MODE_SUMMARY	2

#define ZBX_DO_SUM		0
#define ZBX_DO_MAX		1
#define ZBX_DO_MIN		2
#define ZBX_DO_AVG		3
#define ZBX_DO_ONE		4


#define ZBX_LLD_MACRO_FSNAME		"{#FSNAME}"
#define ZBX_LLD_MACRO_FSTYPE		"{#FSTYPE}"
#define ZBX_LLD_MACRO_FSLABEL		"{#FSLABEL}"
#define ZBX_LLD_MACRO_FSDRIVETYPE	"{#FSDRIVETYPE}"
#define ZBX_LLD_MACRO_FSOPTIONS		"{#FSOPTIONS}"

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
#define ZBX_SYSINFO_TAG_FSOPTIONS		"options"

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

#if defined(_WINDOWS) || defined(__MINGW32__)
typedef int (*zbx_metric_func_t)(AGENT_REQUEST *request, AGENT_RESULT *result, HANDLE timeout_event);
#else
typedef int (*zbx_metric_func_t)(AGENT_REQUEST *request, AGENT_RESULT *result);
#endif

#if !defined(_WINDOWS) && !defined(__MINGW32__)
typedef struct
{
	zbx_uint64_t		total;
	zbx_uint64_t		not_used;
	zbx_uint64_t		used;
	double			pfree;
	double			pused;
}
zbx_fs_metrics_t;

typedef struct
{
	char			fsname[MAX_STRING_LEN];
	char			fstype[MAX_STRING_LEN];
	zbx_fs_metrics_t	bytes;
	zbx_fs_metrics_t	inodes;
	char			*options;
}
zbx_mpoint_t;

typedef struct
{
	char			*mpoint;
	char			*type;
}
zbx_fsname_t;

void	zbx_mpoints_free(zbx_mpoint_t *mpoint);
int	zbx_fsname_compare(const void *fs1, const void *fs2);
#endif

int	sysinfo_get_config_log_remote_commands(void);
int	sysinfo_get_config_unsafe_user_parameters(void);
const char	*sysinfo_get_config_source_ip(void);
const char	*sysinfo_get_config_hostname(void);
const char	*sysinfo_get_config_hostnames(void);
const char	*sysinfo_get_config_host_metadata(void);
const char	*sysinfo_get_config_host_metadata_item(void);
const char	*sysinfo_get_config_service_name(void);

int	zbx_execute_threaded_metric(zbx_metric_func_t metric_func, AGENT_REQUEST *request, AGENT_RESULT *result);

#ifndef _WINDOWS
int	hostname_handle_params(AGENT_REQUEST *request, AGENT_RESULT *result, char **hostname);

typedef struct
{
	zbx_uint64_t	flag;
	const char	*name;
}
zbx_mntopt_t;

char		*zbx_format_mntopt_string(zbx_mntopt_t mntopts[], int flags);
#endif

/* external system functions */
int	get_sensor(AGENT_REQUEST *request, AGENT_RESULT *result);
int	kernel_maxfiles(AGENT_REQUEST *request, AGENT_RESULT *result);
int	kernel_maxproc(AGENT_REQUEST *request, AGENT_RESULT *result);
int	kernel_openfiles(AGENT_REQUEST *request, AGENT_RESULT *result);

#ifdef ZBX_PROCSTAT_COLLECTOR
int	proc_cpu_util(AGENT_REQUEST *request, AGENT_RESULT *result);
#endif

int	proc_get(AGENT_REQUEST *request, AGENT_RESULT *result);
int	proc_mem(AGENT_REQUEST *request, AGENT_RESULT *result);
int	proc_num(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_if_in(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_if_out(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_if_total(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_if_collisions(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_if_discovery(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_tcp_listen(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_tcp_socket_count(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_udp_listen(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_udp_socket_count(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_cpu_switches(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_cpu_intr(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_cpu_load(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_cpu_util(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_cpu_num(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_cpu_discovery(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_hostname(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_hw_chassis(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_hw_cpu(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_hw_devices(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_hw_macaddr(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_sw_arch(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_sw_os(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_sw_os_get(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_sw_packages(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_sw_packages_get(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_swap_in(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_swap_out(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_swap_size(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_uptime(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_uname(AGENT_REQUEST *request, AGENT_RESULT *result);
int	system_boottime(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_dev_read(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_dev_write(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_dev_discovery(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_fs_inode(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_fs_size(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_fs_discovery(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_fs_get(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vm_memory_size(AGENT_REQUEST *request, AGENT_RESULT *result);

#if defined(_WINDOWS) || defined(__MINGW32__)
int	user_perf_counter(AGENT_REQUEST *request, AGENT_RESULT *result);
int	perf_counter(AGENT_REQUEST *request, AGENT_RESULT *result);
int	perf_counter_en(AGENT_REQUEST *request, AGENT_RESULT *result);
int	perf_instance_discovery(AGENT_REQUEST *request, AGENT_RESULT *result);
int	perf_instance_discovery_en(AGENT_REQUEST *request, AGENT_RESULT *result);
int	discover_services(AGENT_REQUEST *request, AGENT_RESULT *result);
int	get_service_info(AGENT_REQUEST *request, AGENT_RESULT *result);
int	get_service_state(AGENT_REQUEST *request, AGENT_RESULT *result);
int	get_list_of_services(AGENT_REQUEST *request, AGENT_RESULT *result);
int	proc_info(AGENT_REQUEST *request, AGENT_RESULT *result);
int	net_if_list(AGENT_REQUEST *request, AGENT_RESULT *result);
int	wmi_get(AGENT_REQUEST *request, AGENT_RESULT *result);
int	wmi_getall(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vm_vmemory_size(AGENT_REQUEST *request, AGENT_RESULT *result);
int	registry_data(AGENT_REQUEST *request, AGENT_RESULT *result);
int	registry_get(AGENT_REQUEST *request, AGENT_RESULT *result);
#endif

int	sysinfo_get_config_timeout(void);

zbx_vector_ptr_t	*get_key_access_rules(void);
#endif /* ZABBIX_SYSINFO_H */
