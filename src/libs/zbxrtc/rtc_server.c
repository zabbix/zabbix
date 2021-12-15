/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "zbxipcservice.h"
#include "zbxjson.h"
#include "zbxrtc.h"
#include "rtc.h"

int	rtc_parse_options_ext(const char *opt, zbx_uint32_t *code, char **data, char **error)
{
	const char	*param;

	if (0 == strcmp(opt, ZBX_SECRETS_RELOAD))
	{
		*code = ZBX_RTC_SECRETS_RELOAD;
		return SUCCEED;
	}

	if (0 == strcmp(opt, ZBX_SERVICE_CACHE_RELOAD))
	{
		*code = ZBX_RTC_SERVICE_CACHE_RELOAD;
		return SUCCEED;
	}

	if (0 == strcmp(opt, ZBX_TRIGGER_HOUSEKEEPER_EXECUTE))
	{
		*code = ZBX_RTC_TRIGGER_HOUSEKEEPER_EXECUTE;
		return SUCCEED;
	}

	if (0 == strcmp(opt, ZBX_HA_STATUS))
	{
		*code = ZBX_RTC_HA_STATUS;
		return SUCCEED;
	}

	if (0 == strncmp(opt, ZBX_HA_REMOVE_NODE, ZBX_CONST_STRLEN(ZBX_HA_REMOVE_NODE)))
	{
		param = opt + ZBX_CONST_STRLEN(ZBX_HA_REMOVE_NODE);

		if ('=' == *param)
		{
			struct zbx_json	j;

			*code = ZBX_RTC_HA_REMOVE_NODE;

			zbx_json_init(&j, 1024);
			zbx_json_addstring(&j, ZBX_PROTO_TAG_NODE, param + 1, ZBX_JSON_TYPE_STRING);
			*data = zbx_strdup(NULL, j.buffer);
			zbx_json_clean(&j);

			return SUCCEED;
		}

		if ('\0' == *param)
		{
			*error = zbx_strdup(NULL, "missing node cuid or name parameter");
			return FAIL;
		}

		/* not ha_remove_node runtime control option */
	}

	if (0 == strncmp(opt, ZBX_HA_SET_FAILOVER_DELAY, ZBX_CONST_STRLEN(ZBX_HA_SET_FAILOVER_DELAY)))
	{
		int	delay;

		param = opt + ZBX_CONST_STRLEN(ZBX_HA_REMOVE_NODE);

		if ('=' == *param)
		{
			if (SUCCEED == is_time_suffix(param + 1, &delay, ZBX_LENGTH_UNLIMITED))
			{
				struct zbx_json	j;

				if (delay < 10 || delay > 15 * SEC_PER_MIN)
				{
					*error = zbx_strdup(NULL, "failover delay must be in range from 10s to 15m");
					return FAIL;
				}

				*code = ZBX_RTC_HA_SET_FAILOVER_DELAY;

				zbx_json_init(&j, 1024);
				zbx_json_addint64(&j, ZBX_PROTO_TAG_FAILOVER_DELAY, delay);
				*data = zbx_strdup(NULL, j.buffer);
				zbx_json_clean(&j);

				return SUCCEED;
			}
			else
			{
				*error = zbx_dsprintf(NULL, "invalid HA failover delay parameter: %s\n", param + 1);
				return FAIL;
			}
		}

		if ('\0' == *param)
		{
			*error = zbx_strdup(NULL, "missing failover delay parameter");
			return FAIL;
		}
	}

	return SUCCEED;
}
