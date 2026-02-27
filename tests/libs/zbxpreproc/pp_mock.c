/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbxtime.h"
#include "zbxvariant.h"
#include "zbxpreprocbase.h"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "pp_mock.h"

int	str_to_preproc_type(const char *str)
{
	if (0 == strcmp(str, "ZBX_PREPROC_MULTIPLIER"))
		return ZBX_PREPROC_MULTIPLIER;
	if (0 == strcmp(str, "ZBX_PREPROC_RTRIM"))
		return ZBX_PREPROC_RTRIM;
	if (0 == strcmp(str, "ZBX_PREPROC_LTRIM"))
		return ZBX_PREPROC_LTRIM;
	if (0 == strcmp(str, "ZBX_PREPROC_TRIM"))
		return ZBX_PREPROC_TRIM;
	if (0 == strcmp(str, "ZBX_PREPROC_REGSUB"))
		return ZBX_PREPROC_REGSUB;
	if (0 == strcmp(str, "ZBX_PREPROC_BOOL2DEC"))
		return ZBX_PREPROC_BOOL2DEC;
	if (0 == strcmp(str, "ZBX_PREPROC_OCT2DEC"))
		return ZBX_PREPROC_OCT2DEC;
	if (0 == strcmp(str, "ZBX_PREPROC_HEX2DEC"))
		return ZBX_PREPROC_HEX2DEC;
	if (0 == strcmp(str, "ZBX_PREPROC_DELTA_VALUE"))
		return ZBX_PREPROC_DELTA_VALUE;
	if (0 == strcmp(str, "ZBX_PREPROC_DELTA_SPEED"))
		return ZBX_PREPROC_DELTA_SPEED;
	if (0 == strcmp(str, "ZBX_PREPROC_XPATH"))
		return ZBX_PREPROC_XPATH;
	if (0 == strcmp(str, "ZBX_PREPROC_JSONPATH"))
		return ZBX_PREPROC_JSONPATH;
	if (0 == strcmp(str, "ZBX_PREPROC_VALIDATE_RANGE"))
		return ZBX_PREPROC_VALIDATE_RANGE;
	if (0 == strcmp(str, "ZBX_PREPROC_VALIDATE_REGEX"))
		return ZBX_PREPROC_VALIDATE_REGEX;
	if (0 == strcmp(str, "ZBX_PREPROC_VALIDATE_NOT_REGEX"))
		return ZBX_PREPROC_VALIDATE_NOT_REGEX;
	if (0 == strcmp(str, "ZBX_PREPROC_ERROR_FIELD_JSON"))
		return ZBX_PREPROC_ERROR_FIELD_JSON;
	if (0 == strcmp(str, "ZBX_PREPROC_ERROR_FIELD_XML"))
		return ZBX_PREPROC_ERROR_FIELD_XML;
	if (0 == strcmp(str, "ZBX_PREPROC_ERROR_FIELD_REGEX"))
		return ZBX_PREPROC_ERROR_FIELD_REGEX;
	if (0 == strcmp(str, "ZBX_PREPROC_THROTTLE_VALUE"))
		return ZBX_PREPROC_THROTTLE_VALUE;
	if (0 == strcmp(str, "ZBX_PREPROC_THROTTLE_TIMED_VALUE"))
		return ZBX_PREPROC_THROTTLE_TIMED_VALUE;
	if (0 == strcmp(str, "ZBX_PREPROC_PROMETHEUS_PATTERN"))
		return ZBX_PREPROC_PROMETHEUS_PATTERN;
	if (0 == strcmp(str, "ZBX_PREPROC_PROMETHEUS_TO_JSON"))
		return ZBX_PREPROC_PROMETHEUS_TO_JSON;
	if (0 == strcmp(str, "ZBX_PREPROC_CSV_TO_JSON"))
		return ZBX_PREPROC_CSV_TO_JSON;
	if (0 == strcmp(str, "ZBX_PREPROC_STR_REPLACE"))
		return ZBX_PREPROC_STR_REPLACE;
	if (0 == strcmp(str, "ZBX_PREPROC_SNMP_WALK_TO_JSON"))
		return ZBX_PREPROC_SNMP_WALK_TO_JSON;
	if (0 == strcmp(str, "ZBX_PREPROC_SNMP_WALK_VALUE"))
		return ZBX_PREPROC_SNMP_WALK_VALUE;
	if (0 == strcmp(str, "ZBX_PREPROC_SNMP_GET_VALUE"))
		return ZBX_PREPROC_SNMP_GET_VALUE;
	if (0 == strcmp(str, "ZBX_PREPROC_SCRIPT"))
		return ZBX_PREPROC_SCRIPT;
	if (0 == strcmp(str, "ZBX_PREPROC_VALIDATE_NOT_SUPPORTED"))
		return ZBX_PREPROC_VALIDATE_NOT_SUPPORTED;

	fail_msg("unknown preprocessing step type: %s", str);
	return FAIL;
}

void	mock_pp_read_variant(zbx_mock_handle_t handle, zbx_variant_t *value)
{
	zbx_mock_handle_t	data_handle, variant_handle;

	if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(handle, "data", &data_handle))
	{
		const char	*data_value;

		zbx_mock_string(data_handle, &data_value);
		zbx_variant_set_str(value, zbx_strdup(NULL, data_value));
	}

	if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(handle, "variant", &variant_handle))
	{
		const char	*variant_value;

		zbx_mock_string(variant_handle, &variant_value);
		zbx_variant_convert(value, zbx_mock_str_to_variant(variant_value));
	}
}

void	mock_pp_read_value(zbx_mock_handle_t handle, unsigned char *value_type, zbx_variant_t *value,
		zbx_timespec_t *ts)
{
	if (NULL != value_type)
		*value_type = zbx_mock_str_to_value_type(zbx_mock_get_object_member_string(handle, "value_type"));
	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_object_member_string(handle, "time"), ts))
		fail_msg("Invalid 'time' format");

	mock_pp_read_variant(handle, value);
}

static int	str_to_preproc_error_handler(const char *str)
{
	if (0 == strcmp(str, "ZBX_PREPROC_FAIL_DEFAULT"))
		return ZBX_PREPROC_FAIL_DEFAULT;
	if (0 == strcmp(str, "ZBX_PREPROC_FAIL_DISCARD_VALUE"))
		return ZBX_PREPROC_FAIL_DISCARD_VALUE;
	if (0 == strcmp(str, "ZBX_PREPROC_FAIL_SET_VALUE"))
		return ZBX_PREPROC_FAIL_SET_VALUE;
	if (0 == strcmp(str, "ZBX_PREPROC_FAIL_SET_ERROR"))
		return ZBX_PREPROC_FAIL_SET_ERROR;

	fail_msg("unknown preprocessing error handler: %s", str);
	return FAIL;
}

void	mock_pp_read_step(zbx_mock_handle_t hop, zbx_pp_step_t *step)
{
	zbx_mock_handle_t	hop_params, herror, herror_params;

	step->type = str_to_preproc_type(zbx_mock_get_object_member_string(hop, "type"));

	if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hop, "params", &hop_params))
		step->params = (char *)zbx_mock_get_object_member_string(hop, "params");
	else
		step->params = "";

	if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hop, "error_handler", &herror))
		step->error_handler = str_to_preproc_error_handler(
				zbx_mock_get_object_member_string(hop, "error_handler"));
	else
		step->error_handler = ZBX_PREPROC_FAIL_DEFAULT;

	if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hop, "error_handler_params", &herror_params))
		step->error_handler_params = (char *)zbx_mock_get_object_member_string(hop, "error_handler_params");
	else
		step->error_handler_params = "";
}
