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

#include "common.h"
#include "sysinfo.h"

#ifdef KERNEL_2_4

#define DO_ONE	0
#define DO_AVG	1
#define DO_MAX	2
#define DO_MIN	3

#define DEVICE_DIR	"/proc/sys/dev/sensors"

static void	count_sensor(int do_task, const char *filename, double *aggr, int *cnt)
{
	FILE	*f;
	char	line[MAX_STRING_LEN];
	double	value;

	if (NULL == (f = fopen(filename, "r")))
		return;

	if (NULL == fgets(line, sizeof(line), f))
	{
		zbx_fclose(f);
		return;
	}

	zbx_fclose(f);

	if (1 == sscanf(line, "%*f\t%*f\t%lf\n", &value))
	{
		(*cnt)++;

		switch (do_task)
		{
			case DO_ONE:
				*aggr = value;
				break;
			case DO_AVG:
				*aggr += value;
				break;
			case DO_MAX:
				*aggr = (1 == *cnt ? value : MAX(*aggr, value));
				break;
			case DO_MIN:
				*aggr = (1 == *cnt ? value : MIN(*aggr, value));
				break;
		}
	}
}

static void	get_device_sensors(int do_task, const char *device, const char *name, double *aggr, int *cnt)
{
	char	sensorname[MAX_STRING_LEN];

	if (DO_ONE == do_task)
	{
		zbx_snprintf(sensorname, sizeof(sensorname), "%s/%s/%s", DEVICE_DIR, device, name);
		count_sensor(do_task, sensorname, aggr, cnt);
	}
	else
	{
		DIR		*devicedir = NULL, *sensordir = NULL;
		struct dirent	*deviceent, *sensorent;
		char		devicename[MAX_STRING_LEN];

		if (NULL == (devicedir = opendir(DEVICE_DIR)))
			return;

		while (NULL != (deviceent = readdir(devicedir)))
		{
			if (0 == strcmp(deviceent->d_name, ".") || 0 == strcmp(deviceent->d_name, ".."))
				continue;

			if (NULL == zbx_regexp_match(deviceent->d_name, device, NULL))
				continue;

			zbx_snprintf(devicename, sizeof(devicename), "%s/%s", DEVICE_DIR, deviceent->d_name);

			if (NULL == (sensordir = opendir(devicename)))
				continue;

			while (NULL != (sensorent = readdir(sensordir)))
			{
				if (0 == strcmp(sensorent->d_name, ".") || 0 == strcmp(sensorent->d_name, ".."))
					continue;

				if (NULL == zbx_regexp_match(sensorent->d_name, name, NULL))
					continue;

				zbx_snprintf(sensorname, sizeof(sensorname), "%s/%s", devicename, sensorent->d_name);
				count_sensor(do_task, sensorname, aggr, cnt);
			}
			closedir(sensordir);
		}
		closedir(devicedir);
	}
}

int	GET_SENSOR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	device[MAX_STRING_LEN], name[MAX_STRING_LEN], function[8];
	int	do_task, cnt = 0;
	double	aggr = 0;

	if (num_param(param) > 3)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, device, sizeof(device)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, name, sizeof(name)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 3, function, sizeof(function)))
		do_task = DO_ONE;
	else if (0 == strcmp(function, "avg"))
		do_task = DO_AVG;
	else if (0 == strcmp(function, "max"))
		do_task = DO_MAX;
	else if (0 == strcmp(function, "min"))
		do_task = DO_MIN;
	else
		return SYSINFO_RET_FAIL;

	get_device_sensors(do_task, device, name, &aggr, &cnt);

	if (0 == cnt)
		return SYSINFO_RET_FAIL;

	if (DO_AVG == do_task)
		SET_DBL_RESULT(result, aggr / cnt);
	else
		SET_DBL_RESULT(result, aggr);

	return SYSINFO_RET_OK;
}

#else

int	GET_SENSOR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return SYSINFO_RET_FAIL;
}

#endif	/* KERNEL_2_4 */
