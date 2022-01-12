/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "common.h"
#include "zbxjson.h"
#include "dbcache.h"
#include "zbxembed.h"
#include "log.h"

#include "../../../src/zabbix_server/preprocessor/item_preproc.h"

zbx_es_t	es_engine;

static int	str_to_preproc_type(const char *str)
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

	fail_msg("unknow preprocessing step type: %s", str);
	return FAIL;
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

	fail_msg("unknow preprocessing error handler: %s", str);
	return FAIL;
}

static void	read_value(const char *path, unsigned char *value_type, zbx_variant_t *value, zbx_timespec_t *ts)
{
	zbx_mock_handle_t	handle;

	handle = zbx_mock_get_parameter_handle(path);
	if (NULL != value_type)
		*value_type = zbx_mock_str_to_value_type(zbx_mock_get_object_member_string(handle, "value_type"));
	zbx_strtime_to_timespec(zbx_mock_get_object_member_string(handle, "time"), ts);
	zbx_variant_set_str(value, zbx_strdup(NULL, zbx_mock_get_object_member_string(handle, "data")));
}

static void	read_history_value(const char *path, zbx_variant_t *value, zbx_timespec_t *ts)
{
	zbx_mock_handle_t	handle;

	handle = zbx_mock_get_parameter_handle(path);
	zbx_strtime_to_timespec(zbx_mock_get_object_member_string(handle, "time"), ts);
	zbx_variant_set_str(value, zbx_strdup(NULL, zbx_mock_get_object_member_string(handle, "data")));
	zbx_variant_convert(value, zbx_mock_str_to_variant(zbx_mock_get_object_member_string(handle, "variant")));
}

static void	read_step(const char *path, zbx_preproc_op_t *op)
{
	zbx_mock_handle_t	hop, hop_params, herror, herror_params;

	hop = zbx_mock_get_parameter_handle(path);
	op->type = str_to_preproc_type(zbx_mock_get_object_member_string(hop, "type"));

	if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hop, "params", &hop_params))
		op->params = (char *)zbx_mock_get_object_member_string(hop, "params");
	else
		op->params = "";

	if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hop, "error_handler", &herror))
		op->error_handler = str_to_preproc_error_handler(zbx_mock_get_object_member_string(hop, "error_handler"));
	else
		op->error_handler = ZBX_PREPROC_FAIL_DEFAULT;

	if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hop, "error_handler_params", &herror_params))
		op->error_handler_params = (char *)zbx_mock_get_object_member_string(hop, "error_handler_params");
	else
		op->error_handler_params = "";
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the preprocessing step is supported based on build      *
 *          configuration or other settings                                   *
 *                                                                            *
 * Parameters: type [IN] the preprocessing step type                          *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing step is supported                *
 *               FAIL    - the preprocessing step is not supported and will   *
 *                         always fail                                        *
 *                                                                            *
 ******************************************************************************/
static int	is_step_supported(int type)
{
	switch (type)
	{
		case ZBX_PREPROC_XPATH:
		case ZBX_PREPROC_ERROR_FIELD_XML:
#ifdef HAVE_LIBXML2
			return SUCCEED;
#else
			return FAIL;
#endif
		default:
			return SUCCEED;
	}
}

void	zbx_mock_test_entry(void **state)
{
	zbx_variant_t			value, history_value;
	unsigned char			value_type;
	zbx_timespec_t			ts, history_ts, expected_history_ts;
	zbx_preproc_op_t		op;
	int				returned_ret, expected_ret;
	char				*error = NULL;

	ZBX_UNUSED(state);

	read_value("in.value", &value_type, &value, &ts);
	read_step("in.step", &op);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.history"))
	{
		read_history_value("in.history", &history_value, &history_ts);
	}
	else
	{
		zbx_variant_set_none(&history_value);
		history_ts.sec = 0;
		history_ts.ns = 0;
	}

	if (FAIL == (returned_ret = zbx_item_preproc(NULL, value_type, &value, &ts, &op, &history_value, &history_ts,
			&error)))
	{
		returned_ret = zbx_item_preproc_handle_error(&value, &op, &error);
	}

	if (SUCCEED != returned_ret)
		zabbix_log(LOG_LEVEL_DEBUG, "Preprocessing error: %s", error);

	if (SUCCEED == is_step_supported(op.type))
		expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	else
		expected_ret = FAIL;

	zbx_mock_assert_result_eq("zbx_item_preproc() return", expected_ret, returned_ret);

	if (SUCCEED == returned_ret)
	{
		if (SUCCEED == is_step_supported(op.type) && ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.error"))
		{
			zbx_mock_assert_str_eq("error message", zbx_mock_get_parameter_string("out.error"), error);
		}
		else
		{
			if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.value"))
			{
				if (ZBX_VARIANT_NONE == value.type)
					fail_msg("preprocessing result was empty value");

				if (ZBX_VARIANT_DBL == value.type)
				{
					zbx_mock_assert_double_eq("processed value",
							atof(zbx_mock_get_parameter_string("out.value")), value.data.dbl);
				}
				else
				{
					zbx_variant_convert(&value, ZBX_VARIANT_STR);
					zbx_mock_assert_str_eq("processed value", zbx_mock_get_parameter_string("out.value"),
							value.data.str);
				}
			}
			else
			{
				if (ZBX_VARIANT_NONE != value.type)
					fail_msg("expected empty value, but got %s", zbx_variant_value_desc(&value));
			}

			if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.history"))
			{
				if (ZBX_VARIANT_NONE == history_value.type)
					fail_msg("preprocessing history was empty value");

				zbx_variant_convert(&history_value, ZBX_VARIANT_STR);
				zbx_mock_assert_str_eq("preprocessing step history value",
						zbx_mock_get_parameter_string("out.history.data"), history_value.data.str);

				zbx_strtime_to_timespec(zbx_mock_get_parameter_string("out.history.time"), &expected_history_ts);
				zbx_mock_assert_timespec_eq("preprocessing step history time", &expected_history_ts, &history_ts);
			}
			else
			{
				if (ZBX_VARIANT_NONE != history_value.type)
					fail_msg("expected empty history, but got %s", zbx_variant_value_desc(&history_value));
			}
		}
	}
	else
		zbx_mock_assert_ptr_ne("error message", NULL, error);

	zbx_variant_clear(&value);
	zbx_variant_clear(&history_value);
	zbx_free(error);

}
