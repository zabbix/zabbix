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

#ifndef ZABBIX_WIN32_H
#define ZABBIX_WIN32_H

#include "config.h"
#include "zbxsysinc.h"
#include "zbxtypes.h"
#include "zbxcommon.h"

#if !defined(_WINDOWS) && !defined(__MINGW32__)
#	error "This module is only available for Windows OS"
#endif

#define	zbx_get_builtin_object_name(ctr)	zbx_get_counter_name(zbx_get_builtin_object_index(ctr))
#define	zbx_get_builtin_counter_name(ctr)	zbx_get_counter_name(zbx_get_builtin_counter_index(ctr))

/* this struct must be only modified along with mapping builtin_counter_ref[] in perfmon.c */
typedef enum
{
	PCI_PROCESSOR_QUEUE_LENGTH = 0,
	PCI_SYSTEM_UP_TIME,
	PCI_PROCESSOR_TIME,
	PCI_INFORMATION_PROCESSOR_TIME,
	PCI_TOTAL_SESSIONS,
	PCI_MAX_INDEX = PCI_TOTAL_SESSIONS
}
zbx_builtin_counter_ref_t;

typedef enum
{
	PERF_COUNTER_NOTSUPPORTED = 0,
	PERF_COUNTER_INITIALIZED,
	PERF_COUNTER_GET_SECOND_VALUE,	/* waiting for the second raw value (needed for some, e.g. rate, counters) */
	PERF_COUNTER_ACTIVE
}
zbx_perf_counter_status_t;

typedef enum
{
	PERF_COUNTER_LANG_DEFAULT = 0,
	PERF_COUNTER_LANG_EN
}
zbx_perf_counter_lang_t;

typedef struct perf_counter_id
{
	struct perf_counter_id	*next;
	unsigned long		pdhIndex;
	wchar_t			name[PDH_MAX_COUNTER_NAME];
}
zbx_perf_counter_id_t;

typedef struct perf_counter_data
{
	struct perf_counter_data	*next;
	char				*name;
	char				*counterpath;
	int				interval;
	zbx_perf_counter_lang_t		lang;
	zbx_perf_counter_status_t	status;
	HCOUNTER			handle;
	PDH_RAW_COUNTER			rawValues[2];	/* rate counters need two raw values */
	int				olderRawValue;	/* index of the older of both values */
	double				*value_array;	/* a circular buffer of values */
	int				value_current;	/* index of the last stored value */
	int				value_count;	/* number of values in the array */
	double				sum;		/* sum of last value_count values */
}
zbx_perf_counter_data_t;

void		zbx_init_library_win32(zbx_get_progname_f get_progname);

zbx_uint64_t	zbx_get_cluster_size(const char *path, char **error);

PDH_STATUS	zbx_PdhMakeCounterPath(const char *function, PDH_COUNTER_PATH_ELEMENTS *cpe, char *counterpath);
PDH_STATUS	zbx_PdhOpenQuery(const char *function, PDH_HQUERY query);
PDH_STATUS	zbx_PdhAddCounter(const char *function, zbx_perf_counter_data_t *counter, PDH_HQUERY query,
		const char *counterpath, zbx_perf_counter_lang_t lang, PDH_HCOUNTER *handle);
PDH_STATUS	zbx_PdhCollectQueryData(const char *function, const char *counterpath, PDH_HQUERY query);
PDH_STATUS	zbx_PdhGetRawCounterValue(const char *function, const char *counterpath, PDH_HCOUNTER handle,
		PPDH_RAW_COUNTER value);

PDH_STATUS	zbx_calculate_counter_value(const char *function, const char *counterpath,
		zbx_perf_counter_lang_t lang, double *value);
wchar_t		*zbx_get_counter_name(DWORD pdhIndex);
int		zbx_check_counter_path(char *counterPath, int convert_from_numeric);
int		zbx_init_builtin_counter_indexes(void);
DWORD		zbx_get_builtin_object_index(zbx_builtin_counter_ref_t counter_ref);
DWORD		zbx_get_builtin_counter_index(zbx_builtin_counter_ref_t counter_ref);
wchar_t		*zbx_get_all_counter_names(HKEY reg_key, wchar_t *reg_value_name);

LONG		zbx_win_seh_handler(struct _EXCEPTION_POINTERS *ep);
#ifdef _M_X64
LONG		zbx_win_veh_handler(struct _EXCEPTION_POINTERS *ep);
#endif /* _M_X64 */

/* symbols */

/* some definitions which are not available on older MS Windows versions */
typedef enum {
	/* we only use below values, the rest of enumerated values are omitted here */
	zbx_FileBasicInfo	= 0,
	zbx_FileIdInfo		= 18
} zbx_file_info_by_handle_class_t;


typedef DWORD	(__stdcall *GetGuiResources_t)(HANDLE, DWORD);
typedef BOOL	(__stdcall *GetProcessIoCounters_t)(HANDLE, PIO_COUNTERS);
typedef BOOL	(__stdcall *GetPerformanceInfo_t)(PPERFORMANCE_INFORMATION, DWORD);
typedef BOOL	(__stdcall *GlobalMemoryStatusEx_t)(LPMEMORYSTATUSEX);
typedef BOOL	(__stdcall *GetFileInformationByHandleEx_t)(HANDLE, zbx_file_info_by_handle_class_t, LPVOID, DWORD);

GetGuiResources_t		zbx_get_GetGuiResources(void);
GetProcessIoCounters_t		zbx_get_GetProcessIoCounters(void);
GetPerformanceInfo_t		zbx_get_GetPerformanceInfo(void);
GlobalMemoryStatusEx_t		zbx_get_GlobalMemoryStatusEx(void);
GetFileInformationByHandleEx_t	zbx_get_GetFileInformationByHandleEx(void);

void	zbx_import_symbols(void);

void	zbx_backtrace(void);
#endif /* ZABBIX_WIN32_H */
