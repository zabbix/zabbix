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

#include "pp_mock.h"

#ifdef HAVE_NETSNMP
#define SNMP_NO_DEBUGGING
#include <net-snmp/net-snmp-config.h>
#include <net-snmp/net-snmp-includes.h>
#endif

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
	zbx_pp_step_t		step;
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

	mock_pp_read_step(zbx_mock_get_parameter_handle("in.step"), &step);

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
		mock_pp_read_value(zbx_mock_get_parameter_handle("in.value"), &value_type, &value_in, &ts);

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
		char		*error = NULL;

		zbx_variant_set_none(&history_value_out);

		zbx_variant_copy(&value, &value_in);
		history_ts = history_ts_in;

		/* run first and last test with no cache */
		if (0 == i || 3 == i || SUCCEED != pp_cache_is_supported(&preproc))
			step_cache = NULL;
		else
			step_cache = cache;

		if (FAIL == (returned_ret = pp_execute_step(&ctx, step_cache, NULL, 0, value_type, &value, ts, &step,
				&history_value_in, &history_value_out, &history_ts, get_zbx_config_source_ip(),
				&error)))
		{
			pp_error_on_fail(NULL, 0, &value, error, &step);

			if (ZBX_VARIANT_ERR != value.type)
				returned_ret = SUCCEED;
		}
		zbx_free(error);

		if (SUCCEED != returned_ret && ZBX_VARIANT_ERR == value.type)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Preprocessing error: %s", value.data.err);
		}

		if (SUCCEED == is_step_supported(step.type))
			expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
		else
			expected_ret = FAIL;

#ifndef HAVE_LIBCURL
		if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.script_uses_curl"))
		{
			pp_cache_release(cache);
			zbx_variant_clear(&value_in);
			zbx_variant_clear(&history_value_in);
			zbx_variant_clear(&value);
			skip();
		}
#endif

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

		if (SUCCEED != zbx_variant_same(&history_value_in, &history_value_out))
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
