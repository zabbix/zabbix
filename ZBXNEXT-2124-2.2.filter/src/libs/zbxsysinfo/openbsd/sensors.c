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
#include "zbxregexp.h"

#include <sys/sensors.h>

#ifdef HAVE_SENSORDEV

#define DO_ONE	0
#define DO_AVG	1
#define DO_MAX	2
#define DO_MIN	3

static void	count_sensor(int do_task, const struct sensor *sensor, double *aggr, int *cnt)
{
	double	value = sensor->value;

	switch (sensor->type)
	{
		case SENSOR_TEMP:
			value = (value - 273150000) / 1000000;
			break;
		case SENSOR_VOLTS_DC:
		case SENSOR_VOLTS_AC:
		case SENSOR_AMPS:
		case SENSOR_LUX:
			value /= 1000000;
			break;
		case SENSOR_TIMEDELTA:
			value /= 1000000000;
			break;
		default:
			break;
	}

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

static int	get_device_sensors(int do_task, int *mib, const struct sensordev *sensordev, const char *name, double *aggr, int *cnt)
{
	if (DO_ONE == do_task)
	{
		int		i, len = 0;
		struct sensor	sensor;
		size_t		slen = sizeof(sensor);

		for (i = 0; i < SENSOR_MAX_TYPES; i++)
		{
			if (0 == strncmp(name, sensor_type_s[i], len = strlen(sensor_type_s[i])))
				break;
		}

		if (i == SENSOR_MAX_TYPES)
			return FAIL;

		if (SUCCEED != is_uint31(name + len, (uint32_t*)&mib[4]))
			return FAIL;

		mib[3] = i;

		if (-1 == sysctl(mib, 5, &sensor, &slen, NULL, 0))
			return FAIL;

		count_sensor(do_task, &sensor, aggr, cnt);
	}
	else
	{
		int	i, j;

		for (i = 0; i < SENSOR_MAX_TYPES; i++)
		{
			for (j = 0; j < sensordev->maxnumt[i]; j++)
			{
				char		human[64];
				struct sensor	sensor;
				size_t		slen = sizeof(sensor);

				zbx_snprintf(human, sizeof(human), "%s%d", sensor_type_s[i], j);

				if (NULL == zbx_regexp_match(human, name, NULL))
					continue;

				mib[3] = i;
				mib[4] = j;

				if (-1 == sysctl(mib, 5, &sensor, &slen, NULL, 0))
					return FAIL;

				count_sensor(do_task, &sensor, aggr, cnt);
			}
		}
	}

	return SUCCEED;
}

int	GET_SENSOR(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*device, *name, *function;
	int	do_task, mib[5], dev, cnt = 0;
	double	aggr = 0;

	if (3 < request->nparam)
		return SYSINFO_RET_FAIL;

	device = get_rparam(request, 0);
	name = get_rparam(request, 1);
	function = get_rparam(request, 2);

	if (NULL == device || NULL == name)
		return SYSINFO_RET_FAIL;

	if (NULL == function || '\0' == *function)
		do_task = DO_ONE;
	else if (0 == strcmp(function, "avg"))
		do_task = DO_AVG;
	else if (0 == strcmp(function, "max"))
		do_task = DO_MAX;
	else if (0 == strcmp(function, "min"))
		do_task = DO_MIN;
	else
		return SYSINFO_RET_FAIL;

	mib[0] = CTL_HW;
	mib[1] = HW_SENSORS;

	for (dev = 0;; dev++)
	{
		struct sensordev	sensordev;
		size_t			sdlen = sizeof(sensordev);

		mib[2] = dev;

		if (-1 == sysctl(mib, 3, &sensordev, &sdlen, NULL, 0))
		{
			if (errno == ENXIO)
				continue;
			if (errno == ENOENT)
				break;

			return SYSINFO_RET_FAIL;
		}

		if ((DO_ONE == do_task && 0 == strcmp(sensordev.xname, device)) ||
				(DO_ONE != do_task && NULL != zbx_regexp_match(sensordev.xname, device, NULL)))
		{
			if (SUCCEED != get_device_sensors(do_task, mib, &sensordev, name, &aggr, &cnt))
				return SYSINFO_RET_FAIL;
		}
	}

	if (0 == cnt)
		return SYSINFO_RET_FAIL;

	if (DO_AVG == do_task)
		SET_DBL_RESULT(result, aggr / cnt);
	else
		SET_DBL_RESULT(result, aggr);

	return SYSINFO_RET_OK;
}

#else

int	GET_SENSOR(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return SYSINFO_RET_FAIL;
}

#endif	/* HAVE_SENSORDEV */
