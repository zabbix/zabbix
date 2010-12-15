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

#include "common.h"

#include "sysinfo.h"

#include "md5.h"

#include <sys/sensors.h>

#define CELSIUS(x) ((x - 273150000) / 1000000.0)

int	sensor_value(int[], struct sensor *, char *);

int	sensor_value(int mib[], struct sensor *sensor, char *key2)
{
	size_t		slen;

	mib[3] = SENSOR_TEMP;
	mib[4] = key2 ? atoi(key2) : 0;

	slen = sizeof(*sensor);
	if (sysctl(mib, 5, sensor, &slen, NULL, 0) == -1)
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	GET_SENSOR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	enum sensor_type	 type;
	struct sensordev 	 sensordev;
	struct sensor		 sensor;
	size_t			 sdlen = sizeof(sensordev);
	int			 mib[3], dev, numt, cnt = 0, ret = SYSINFO_RET_FAIL;
	uint64_t	 	 aggr = 0;
	char			 key[MAX_STRING_LEN], key2[MAX_STRING_LEN];

	assert(result);

	init_result(result);

	if(num_param(param) > 2)
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 1, key, MAX_STRING_LEN) != 0)
	{
		return SYSINFO_RET_FAIL;
	}

	if(num_param(param) == 2 && get_param(param, 2, key2, MAX_STRING_LEN) != 0)
	{
		return SYSINFO_RET_FAIL;
	}

	mib[0] = CTL_HW;
	mib[1] = HW_SENSORS;

	for (dev = 0; ; dev++)
	{
		mib[2] = dev;

		if (sysctl(mib, 3, &sensordev, &sdlen, NULL, 0) == -1)
		{
			if (errno == ENXIO)
				continue;
			if (errno == ENOENT)
				break;

			return SYSINFO_RET_FAIL;
		}

		if (!strcmp(key, "") || !strcmp(key, "cpu"))
		{
			if (!strncmp(sensordev.xname, "cpu", 3))
			{
				ret = sensor_value(mib, &sensor, NULL);
				aggr += sensor.value;
				cnt++;
			}
		}
		else
		{
			if (!strcmp(sensordev.xname, key))
			{
				ret = sensor_value(mib, &sensor, key2);
				if (sensor.type == SENSOR_TEMP)
					SET_DBL_RESULT(result, CELSIUS(sensor.value));
			}
		}
	}

	if ((!strcmp(key, "") || !strcmp(key, "cpu")) && cnt)
		SET_DBL_RESULT(result, CELSIUS(aggr / cnt));

	return ret;
}
