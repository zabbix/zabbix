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

#include "rtc_proxy.h"

#include "zbxcommon.h"
#include "zbx_rtc_constants.h"
#include "zbxdiag.h"
#include "zbxjson.h"


/******************************************************************************
 *                                                                            *
 * Purpose: process diaginfo runtime control option                           *
 *                                                                            *
 * Parameters: data   - [IN] the runtime control parameter (optional)         *
 *             result - [OUT] the runtime control result                      *
 *                                                                            *
 * Return value: SUCCEED - the rtc command was processed                      *
 *               FAIL    - the rtc command must be processed by the default   *
 *                         rtc command handler                                *
 *                                                                            *
 ******************************************************************************/
static int	rtc_process_diaginfo(const char *data, char **result)
{
	struct zbx_json_parse	jp;
	char			buf[MAX_STRING_LEN];
	unsigned int		scope = 0;
	int			ret = FAIL;

	if (FAIL == zbx_json_open(data, &jp) ||
			SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_SECTION, buf, sizeof(buf), NULL))
	{
		*result = zbx_dsprintf(NULL, "Invalid parameter \"%s\"\n", data);
		return FAIL;
	}

	if (0 == strcmp(buf, "all"))
	{
		scope = 1 << ZBX_DIAGINFO_PROXYBUFFER;
	}
	else if (0 == strcmp(buf, ZBX_DIAG_PROXYBUFFER))
	{
		scope = 1 << ZBX_DIAGINFO_PROXYBUFFER;
		ret = SUCCEED;
	}

	if (0 != scope)
		zbx_diag_log_info(scope, result);

	return ret;
}

int	rtc_process_request_ex_proxy(zbx_rtc_t *rtc, zbx_uint32_t code, const unsigned char *data,
		char **result)
{
	ZBX_UNUSED(rtc);
	ZBX_UNUSED(data);
	ZBX_UNUSED(result);

	switch (code)
	{
		case ZBX_RTC_DIAGINFO:
			return rtc_process_diaginfo((const char *)data, result);
	}

	return FAIL;
}

int	rtc_process_request_ex_proxy_passive(zbx_rtc_t *rtc, zbx_uint32_t code, const unsigned char *data, char **result)
{
	ZBX_UNUSED(data);
	ZBX_UNUSED(result);

	switch (code)
	{
		case ZBX_RTC_CONFIG_CACHE_RELOAD:
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_TASKMANAGER, 0, ZBX_RTC_CONFIG_CACHE_RELOAD, NULL, 0);
			return SUCCEED;
		default:
			return rtc_process_request_ex_proxy(rtc, code, data, result);
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
