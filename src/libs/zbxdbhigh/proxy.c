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

#include "zbxdbhigh.h"
#include "zbxjson.h"
#include "zbxversion.h"

void	zbx_calc_timestamp(const char *line, int *timestamp, const char *format)
{
	int		hh, mm, ss, yyyy, dd, MM;
	int		hhc = 0, mmc = 0, yyyyc = 0, ddc = 0, MMc = 0;
	int		i, num;
	struct tm	tm;
	time_t		t;


	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	hh = mm = ss = yyyy = dd = MM = 0;

	for (i = 0; '\0' != format[i] && '\0' != line[i]; i++)
	{
		if (0 == isdigit(line[i]))
			continue;

		num = (int)line[i] - 48;

		switch ((char)format[i])
		{
			case 'h':
				hh = 10 * hh + num;
				hhc++;
				break;
			case 'm':
				mm = 10 * mm + num;
				mmc++;
				break;
			case 's':
				ss = 10 * ss + num;
				break;
			case 'y':
				yyyy = 10 * yyyy + num;
				yyyyc++;
				break;
			case 'd':
				dd = 10 * dd + num;
				ddc++;
				break;
			case 'M':
				MM = 10 * MM + num;
				MMc++;
				break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() %02d:%02d:%02d %02d/%02d/%04d", __func__, hh, mm, ss, MM, dd, yyyy);

	/* seconds can be ignored, no ssc here */
	if (0 != hhc && 0 != mmc && 0 != yyyyc && 0 != ddc && 0 != MMc)
	{
		tm.tm_sec = ss;
		tm.tm_min = mm;
		tm.tm_hour = hh;
		tm.tm_mday = dd;
		tm.tm_mon = MM - 1;
		tm.tm_year = yyyy - 1900;
		tm.tm_isdst = -1;

		if (0 < (t = mktime(&tm)))
			*timestamp = t;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() timestamp:%d", __func__, *timestamp);
}

/******************************************************************************
 *                                                                            *
 * Purpose: extracts protocol version from json data                          *
 *                                                                            *
 * Parameters:                                                                *
 *     jp      - [IN] JSON with the proxy version                             *
 *                                                                            *
 * Return value: The protocol version in textual representation, for example, *
 *               "6.4.0alpha1",                                               *
 *     actual proxy version - if proxy version was successfully extracted     *
 *     undefined version    - otherwise                                       *
 *                                                                            *
 * Comments: allocates memory                                                 *
 *                                                                            *
 ******************************************************************************/
char	*zbx_get_proxy_protocol_version_str(const struct zbx_json_parse *jp)
{
	char	value[MAX_STRING_LEN];

	if (NULL != jp && SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_VERSION, value, sizeof(value), NULL))
		return strdup(value);

	return strdup(ZBX_VERSION_UNDEFINED_STR);
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts protocol version from textual to numeric representation  *
 *          for version comparison. The function truncates release candidate  *
 *          part of the version.                                              *
 *                                                                            *
 * Parameters:                                                                *
 *     version_str - [IN] proxy version, for example "6.4.0alpha1".           *
 *                                                                            *
 * Return value: The protocol version in numeric representation, for example, *
 *               060400                                                       *
 *     actual proxy version - if proxy version was successfully extracted     *
 *     proxy version 3.2    - otherwise                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_proxy_protocol_version_int(const char *version_str)
{
	int	version_int;

	if (0 != strcmp(ZBX_VERSION_UNDEFINED_STR, version_str) &&
			FAIL != (version_int = zbx_get_component_version(version_str)))
	{
		return version_int;
	}

	return ZBX_COMPONENT_VERSION(3, 2, 0);
}

