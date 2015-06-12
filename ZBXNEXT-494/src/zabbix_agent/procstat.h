/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#ifndef ZABBIX_PROCSTAT_H
#define ZABBIX_PROCSTAT_H

#ifdef ZBX_PROCSTAT_COLLECTOR

#define ZBX_PROCSTAT_CPU_USER			1
#define ZBX_PROCSTAT_CPU_SYSTEM			2
#define ZBX_PROCSTAT_CPU_TOTAL			3

#define ZBX_PROCSTAT_FLAGS_ZONE_CURRENT		0
#define ZBX_PROCSTAT_FLAGS_ZONE_ALL		1


/* process cpu utilization data */
typedef struct
{
	pid_t		pid;

	/* errno error code */
	int		error;

	zbx_uint64_t	utime;
	zbx_uint64_t	stime;

	/* process start time, used to validate if the old */
	/* snapshot data belongs to the same process       */
	zbx_uint64_t	starttime;
}
zbx_procstat_util_t;

void	zbx_procstat_init();

int	zbx_procstat_collector_enabled();

int	zbx_procstat_enabled();

int	zbx_procstat_get_util(const char *procname, const char *username, const char *cmdline, zbx_uint64_t flags,
		int period, int type, double *value, char **errmsg);

void	zbx_procstat_collect();

#endif

#endif
