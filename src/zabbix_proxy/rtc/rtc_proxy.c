/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "rtc_proxy.h"

#include "zbxdbwrap.h"
#include "zbx_rtc_constants.h"

int	rtc_process_request_ex_passive(zbx_rtc_t *rtc, zbx_uint32_t code, const unsigned char *data, char **result)
{
	ZBX_UNUSED(data);
	ZBX_UNUSED(result);

	switch (code)
	{
		case ZBX_RTC_CONFIG_CACHE_RELOAD:
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_TASKMANAGER, 0, ZBX_RTC_CONFIG_CACHE_RELOAD, NULL, 0);
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process runtime control option and print result                   *
 *                                                                            *
 * Parameters: option         - [IN] the runtime control option               *
 *             config_timeout - [IN]                                          *
 *             error          - [OUT] error message                           *
 *                                                                            *
 * Return value: SUCCEED - the runtime control option was processed           *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	rtc_process(const char *option, int config_timeout, char **error)
{
	zbx_uint32_t	code = ZBX_RTC_UNKNOWN;
	char		*data = NULL;
	int		ret = FAIL;
	struct zbx_json	j;

	zbx_json_init(&j, 1024);

	if (SUCCEED != zbx_rtc_parse_options(option, &code, &j, error))
		goto out;

	if (ZBX_RTC_UNKNOWN == code)
	{
		*error = zbx_dsprintf(NULL, "unknown option \"%s\"", option);
		goto out;
	}

	data = zbx_strdup(NULL, j.buffer);
	ret = zbx_rtc_async_exchange(&data, code, config_timeout, error);
out:
	zbx_json_free(&j);

	return ret;
}
