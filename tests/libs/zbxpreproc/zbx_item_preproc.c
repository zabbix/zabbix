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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxcommon.h"
#include "zbxjson.h"
#include "zbxcacheconfig.h"
#include "zbxembed.h"
#include "zbxlog.h"
#include "zbxpreproc.h"
#include "libs/zbxpreproc/pp_execute.h"
#include "libs/zbxpreproc/preproc_snmp.h"
#include "libs/zbxpreproc/pp_cache.h"
#include "libs/zbxpreproc/pp_error.h"

#ifdef HAVE_NETSNMP
#define SNMP_NO_DEBUGGING
#include <net-snmp/net-snmp-config.h>
#include <net-snmp/net-snmp-includes.h>
#endif

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

static void	read_value(const char *path, unsigned char *value_type, zbx_variant_t *value, zbx_timespec_t *ts)
{
	zbx_mock_handle_t	handle;

	handle = zbx_mock_get_parameter_handle(path);
	if (NULL != value_type)
		*value_type = zbx_mock_str_to_value_type(zbx_mock_get_object_member_string(handle, "value_type"));
	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_object_member_string(handle, "time"), ts))
		fail_msg("Invalid 'time' format");

	zbx_variant_set_str(value, zbx_strdup(NULL, zbx_mock_get_object_member_string(handle, "data")));
}

static void	read_history_value(const char *path, zbx_variant_t *value, zbx_timespec_t *ts)
{
	zbx_mock_handle_t	handle;

	handle = zbx_mock_get_parameter_handle(path);
	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_object_member_string(handle, "time"), ts))
		fail_msg("Invalid 'time' format");

	zbx_variant_set_str(value, zbx_strdup(NULL, zbx_mock_get_object_member_string(handle, "data")));
	zbx_variant_convert(value, zbx_mock_str_to_variant(zbx_mock_get_object_member_string(handle, "variant")));
}

static void	read_error(const char *path, zbx_variant_t *value, zbx_timespec_t *ts)
{
	zbx_mock_handle_t	handle;

	handle = zbx_mock_get_parameter_handle(path);
	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_object_member_string(handle, "time"), ts))
		fail_msg("Invalid 'time' format");

	zbx_variant_set_error(value, zbx_strdup(NULL, zbx_mock_get_object_member_string(handle, "data")));
}

static void	read_step(const char *path, zbx_pp_step_t *step)
{
	zbx_mock_handle_t	hop, hop_params, herror, herror_params;

	hop = zbx_mock_get_parameter_handle(path);
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

static void	duplicate_step(zbx_pp_step_t *step_src, zbx_pp_step_t *step_dst)
{
	step_dst->type = step_src->type;
	step_dst->params = zbx_strdup(NULL, step_src->params);
	step_dst->error_handler = step_src->error_handler;
	step_dst->error_handler_params = zbx_strdup(NULL, step_src->error_handler_params);
}

static void	release_step(zbx_pp_step_t *step)
{
	zbx_free(step->params);
	zbx_free(step->error_handler_params);
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

#ifdef HAVE_NETSNMP
/******************************************************************************
 *                                                                            *
 * Purpose: checks if MIB can be translated to OID by the system. Absence of  *
 *          MIB files in the system will cause failures of MIB translation    *
 *          tests, and such tests should be skipped if MIB was not found.     *
 *          configuration or other settings                                   *
 *                                                                            *
 * Parameters: op [IN] the preprocessing step operation                       *
 *                                                                            *
 * Return value: SUCCEED - MIB can be translated / MIB file exists            *
 *               FAIL    - MIB cannot be translated / MIB file doesn't exist  *
 *                                                                            *
 ******************************************************************************/
static int	check_mib_existence(zbx_pp_step_t *op)
{
	int		ret = FAIL;
	oid		oid_tmp[MAX_OID_LEN];
	size_t		oid_len = MAX_OID_LEN;
	char		*oid_str = NULL, *right = NULL;

	if (ZBX_PREPROC_SNMP_WALK_VALUE == op->type)
	{
		zbx_strsplit_first(op->params, '\n', &oid_str, &right);
	}
	else if (ZBX_PREPROC_SNMP_WALK_TO_JSON == op->type)
	{
		char	*ptr = op->params;
		int	line_idx = 0;

		while (line_idx != 1 && '\0' != *ptr)
		{
			if ('\n' == *ptr)
				line_idx++;

			ptr++;
		}

		zbx_strsplit_first(ptr, '\n', &oid_str, &right);
	}
	else
		fail_msg("processing operation type %i is not compatible with SNMP preprocessing", op->type);

	if (0 != get_node(oid_str, oid_tmp, &oid_len))
		ret = SUCCEED;

	zbx_free(oid_str);
	zbx_free(right);

	return ret;
}
#endif

#ifdef HAVE_NETSNMP
ZBX_GET_CONFIG_VAR2(const char *, const char *, zbx_progname, "preproc_mock_progname")
#endif

void	zbx_mock_test_entry(void **state)
{
	zbx_variant_t		value, value_in, history_value_in;
	unsigned char		value_type;
	zbx_timespec_t		ts, history_ts, history_ts_in, expected_history_ts;
	zbx_pp_step_t		step, step_orig;
	int			returned_ret, expected_ret, i;
	zbx_pp_context_t	ctx = {0};
	zbx_pp_cache_t		*cache, *step_cache;
	zbx_pp_item_preproc_t	preproc;

	pp_context_init(&ctx);

#ifdef HAVE_NETSNMP
	int			mib_translation_case = 0;

	zbx_init_library_preproc(NULL, NULL, get_zbx_progname);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.netsnmp_required"))
		mib_translation_case = 1;
#else
	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.netsnmp_required"))
		skip();
#endif

#if !defined(HAVE_OPENSSL) && !defined(HAVE_GNUTLS)
	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.encryption_required"))
		skip();
#endif

	ZBX_UNUSED(state);

	read_step("in.step", &step_orig);
	duplicate_step(&step_orig, &step);

#ifdef HAVE_NETSNMP
	preproc_init_snmp();

	/* MIB translation test cases will fail if system lacks MIBs - in this case test case should be skipped */
	if (1 == mib_translation_case && FAIL == check_mib_existence(&step))
	{
		preproc_shutdown_snmp();
		release_step(&step);
		skip();
	}
#endif

	pp_context_init(&ctx);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.error"))
	{
		read_error("in.error", &value_in, &ts);
		value_type = value_in.type;
	}
	else
		read_value("in.value", &value_type, &value_in, &ts);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.history"))
	{
		read_history_value("in.history", &history_value_in, &history_ts_in);
	}
	else
	{
		zbx_variant_set_none(&history_value_in);
		history_ts_in.sec = 0;
		history_ts_in.ns = 0;
	}

	preproc.steps = &step;
	preproc.steps_num = 1;
	cache = pp_cache_create(&preproc, &value_in);

	for (i = 0; i < 4; i++)
	{
		zbx_variant_t	history_value_out;

		zbx_variant_set_none(&history_value_out);

		zbx_variant_copy(&value, &value_in);
		history_ts = history_ts_in;

		/* run first and last test with no cache */
		if (0 == i || 3 == i || SUCCEED != pp_cache_is_supported(&preproc))
			step_cache = NULL;
		else
			step_cache = cache;

		if (FAIL == (returned_ret = pp_execute_step(&ctx, step_cache, NULL, 0, value_type, &value, ts, &step,
				&history_value_in, &history_value_out, &history_ts, get_zbx_config_source_ip())))
		{
			pp_error_on_fail(NULL, 0, &value, &step);

			if (ZBX_VARIANT_ERR != value.type)
				returned_ret = SUCCEED;
		}

		if (SUCCEED != returned_ret && ZBX_VARIANT_ERR == value.type)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Preprocessing error: %s", value.data.err);
		}

		if (SUCCEED == is_step_supported(step.type))
			expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
		else
			expected_ret = FAIL;

		zbx_mock_assert_result_eq("zbx_item_preproc() return", expected_ret, returned_ret);

		if (SUCCEED == returned_ret)
		{
			if (SUCCEED == is_step_supported(step.type) &&
					ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.error"))
			{
				zbx_mock_assert_int_eq("result variant type", ZBX_VARIANT_ERR, value.type);
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
								atof(zbx_mock_get_parameter_string("out.value")),
								value.data.dbl);
					}
					else
					{
						zbx_variant_convert(&value, ZBX_VARIANT_STR);
						zbx_mock_assert_str_eq("processed value",
								zbx_mock_get_parameter_string("out.value"), value.data.str);
					}
				}
				else
				{
					if (ZBX_VARIANT_NONE != value.type)
						fail_msg("expected empty value, but got %s", zbx_variant_value_desc(&value));
				}

				if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.history"))
				{
					if (ZBX_VARIANT_NONE == history_value_out.type)
						fail_msg("preprocessing history was empty value");

					zbx_variant_convert(&history_value_out, ZBX_VARIANT_STR);
					zbx_mock_assert_str_eq("preprocessing step history value",
							zbx_mock_get_parameter_string("out.history.data"),
							history_value_out.data.str);

					zbx_strtime_to_timespec(zbx_mock_get_parameter_string("out.history.time"),
							&expected_history_ts);
					zbx_mock_assert_timespec_eq("preprocessing step history time", &expected_history_ts,
							&history_ts);
				}
				else
				{
					/* history_value will contain duktape bytecode if step is a script */
					if (ZBX_VARIANT_NONE != history_value_out.type && ZBX_PREPROC_SCRIPT != step.type)
					{
						fail_msg("expected empty history, but got %s",
								zbx_variant_value_desc(&history_value_out));
					}
				}
			}
		}
		else
			zbx_mock_assert_int_eq("result variant type", ZBX_VARIANT_ERR, value.type);

		zbx_variant_clear(&value);
		zbx_variant_clear(&history_value_out);
	}

	pp_cache_release(cache);
	zbx_variant_clear(&value_in);
	zbx_variant_clear(&history_value_in);

	pp_context_destroy(&ctx);
#ifdef HAVE_NETSNMP
	preproc_shutdown_snmp();
#endif
	release_step(&step);
}
