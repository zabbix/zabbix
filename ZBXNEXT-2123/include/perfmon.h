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

#ifndef ZABBIX_PERFMON_H
#define ZABBIX_PERFMON_H

#ifndef _WINDOWS
#	error "This module is only available for Windows OS"
#endif

#define PCI_SYSTEM			2
#define PCI_PROCESSOR			238
#define PCI_PROCESSOR_TIME		6
#define PCI_PROCESSOR_QUEUE_LENGTH	44
#define PCI_SYSTEM_UP_TIME		674
#define PCI_TERMINAL_SERVICES		2176
#define PCI_TOTAL_SESSIONS		2178

typedef enum
{
	PERF_COUNTER_NOTSUPPORTED = 0,
	PERF_COUNTER_INITIALIZED,
	PERF_COUNTER_GET_SECOND_VALUE,	/* waiting for the second raw value (needed for some, e.g. rate, counters) */
	PERF_COUNTER_ACTIVE,
};

typedef struct perf_counter_id
{
	struct perf_counter_id	*next;
	unsigned long		pdhIndex;
	TCHAR			name[PDH_MAX_COUNTER_NAME];
}
PERF_COUNTER_ID;

typedef struct perf_counter_data
{
	struct perf_counter_data	*next;
	char				*name;
	char				*counterpath;
	int				interval;
	int				status;
	HCOUNTER			handle;
	PDH_RAW_COUNTER			rawValues[2];	/* rate counters need two raw values */
	int				olderRawValue;	/* index of the older of both values */
	double				*value_array;	/* a circular buffer of values */
	int				value_current;	/* index of the last stored value */
	int				value_count;	/* number of values in the array */
	double				sum;		/* sum of last value_count values */
}
PERF_COUNTER_DATA;

PDH_STATUS	zbx_PdhMakeCounterPath(const char *function, PDH_COUNTER_PATH_ELEMENTS *cpe, char *counterpath);
PDH_STATUS	zbx_PdhOpenQuery(const char *function, PDH_HQUERY query);
PDH_STATUS	zbx_PdhAddCounter(const char *function, PERF_COUNTER_DATA *counter, PDH_HQUERY query,
		const char *counterpath, PDH_HCOUNTER *handle);
PDH_STATUS	zbx_PdhCollectQueryData(const char *function, const char *counterpath, PDH_HQUERY query);
PDH_STATUS	zbx_PdhGetRawCounterValue(const char *function, const char *counterpath, PDH_HCOUNTER handle, PPDH_RAW_COUNTER value);

PDH_STATUS	calculate_counter_value(const char *function, const char *counterpath, double *value);
LPTSTR		get_counter_name(DWORD pdhIndex);
int		check_counter_path(char *counterPath);

#endif /* ZABBIX_PERFMON_H */
