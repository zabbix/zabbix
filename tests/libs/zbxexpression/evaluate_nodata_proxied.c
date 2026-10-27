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

#include "zbxcachevalue.h"
#include "zbxcacheconfig.h"
#include "zbxcachehistory.h"
#include "zbxhistory.h"
#include "zbxexpression.h"
#include "../../src/libs/zbxexpression/evalfunc.h"

#include "zbxnum.h"
#include "zbxvariant.h"
#include "zbx_item_constants.h"

#include "mocks/valuecache/valuecache_mock.h"
#include "../../../src/libs/zbxtrends/trends.h"

/* nodata() on a proxied item in lazy mode also consults the proxy's suppression window                  */
/* (zbx_dc_get_proxy_nodata_win) and the history cache tail state (zbx_hc_is_itemid_cached_and_normal(), */
/* fixed in cachehistory.c) to decide whether it might still be waiting on data still in transit from    */
/* the proxy. zbx_dc_get_proxy_nodata_win() is wrapped (there is no lightweight way to fake a proxy's    */
/* real DC config cache state here), but zbx_hc_is_itemid_cached_and_normal() itself is NOT wrapped -    */
/* instead a real, minimal history cache is initialized (zbx_init_database_cache()) and a real tail      */
/* record is pushed into it through the actual production write path (zbx_dc_add_history_variant() +     */
/* zbx_dc_flush_history()), so evaluate_NODATA() ends up calling the real, unmodified fixed function.    */
/* zbx_hashset_search() itself is deliberately left unwrapped for this reason too - wrapping it would    */
/* also hijack the value cache's own internal item index used by every other test case in this file.     */
/* These knobs are all optional and default to values that reproduce the pre-existing (proxyid == 0)     */
/* behaviour untouched, so none of the other function test cases here are affected.                      */

static unsigned char	mock_proxy_flags;
static int		mock_proxy_lastaccess_age;

static void	check_expected_error(const char *actual_error)
{
	zbx_mock_handle_t	param_handle;
	const char		*expected_error;
	zbx_mock_error_t	mock_ret;

	mock_ret = zbx_mock_out_parameter("error", &param_handle);

	if (ZBX_MOCK_SUCCESS != mock_ret)
		fail_msg("Cannot get expected 'error' parameter: %s", zbx_mock_error_string(mock_ret));

	mock_ret = zbx_mock_string(param_handle, &expected_error);

	if (ZBX_MOCK_SUCCESS != mock_ret)
		fail_msg("Cannot read expected 'error' string: %s", zbx_mock_error_string(mock_ret));

	if (NULL == actual_error)
		fail_msg("Expected error '%s' but got NULL", expected_error);

	if (0 != strcmp(actual_error, expected_error))
		fail_msg("Got\n'%s'\ninstead of\n'%s'", actual_error, expected_error);
}

static int	get_optional_parameter_int(const char *path, int default_value)
{
	const char	*value;

	if (NULL == (value = zbx_mock_get_optional_parameter_string(path)))
		return default_value;

	return atoi(value);
}

/* Mirrors zbx_mock_str_to_value_type()'s pattern (tests/zbxmockutil.c) so item state reads as a name */
/* in the yaml (e.g. "ITEM_STATE_NOTSUPPORTED") instead of an opaque 0/1 integer.                     */
static unsigned char	str_to_item_state(const char *str)
{
	if (0 == strcmp(str, "ITEM_STATE_NORMAL"))
		return ITEM_STATE_NORMAL;

	if (0 == strcmp(str, "ITEM_STATE_NOTSUPPORTED"))
		return ITEM_STATE_NOTSUPPORTED;

	fail_msg("Unknown item state \"%s\"", str);

	return ITEM_STATE_NORMAL;
}

static unsigned char	get_optional_item_state(const char *path, unsigned char default_value)
{
	const char	*value;

	if (NULL == (value = zbx_mock_get_optional_parameter_string(path)))
		return default_value;

	return str_to_item_state(value);
}

/* Reads a yaml sequence of flag names (e.g. "[ZBX_PROXY_SUPPRESS_ACTIVE, ZBX_PROXY_SUPPRESS_MORE]") */
/* and ORs them together via a function passed in 'name_to_bit' argument, so multi-bit fields read   */
/* as names instead of an opaque combined integer. An absent key or an empty list both mean          */
/* "no flags set".                                                                                   */
static unsigned char	get_optional_flag_list(const char *path, unsigned char (*name_to_bit)(const char *))
{
	zbx_mock_handle_t	handle, element;
	zbx_mock_error_t	err;
	unsigned char		flags = 0;
	const char		*name;

	if (ZBX_MOCK_SUCCESS != zbx_mock_parameter(path, &handle))
		return 0;

	while (ZBX_MOCK_END_OF_VECTOR != (err = zbx_mock_vector_element(handle, &element)))
	{
		if (ZBX_MOCK_SUCCESS != err || ZBX_MOCK_SUCCESS != zbx_mock_string(element, &name))
			fail_msg("Cannot read an element of \"%s\": %s", path, zbx_mock_error_string(err));

		flags |= name_to_bit(name);
	}

	return flags;
}

static unsigned char	str_to_proxy_suppress_flag(const char *str)
{
	if (0 == strcmp(str, "ZBX_PROXY_SUPPRESS_ACTIVE"))
		return ZBX_PROXY_SUPPRESS_ACTIVE;

	if (0 == strcmp(str, "ZBX_PROXY_SUPPRESS_MORE"))
		return ZBX_PROXY_SUPPRESS_MORE;

	if (0 == strcmp(str, "ZBX_PROXY_SUPPRESS_EMPTY"))
		return ZBX_PROXY_SUPPRESS_EMPTY;

	fail_msg("Unknown proxy suppress flag \"%s\"", str);

	return 0;
}

/* only ZBX_DC_FLAG_NOVALUE is actually actioned by zbx_vcmock_push_history_tail() below - the fix    */
/* under test only inspects that one bit - but the full name set is recognised so a yaml author can't */
/* silently typo a flag name into a no-op.                                                            */
static unsigned char	str_to_dc_flag(const char *str)
{
	if (0 == strcmp(str, "ZBX_DC_FLAG_META"))
		return ZBX_DC_FLAG_META;

	if (0 == strcmp(str, "ZBX_DC_FLAG_NOVALUE"))
		return ZBX_DC_FLAG_NOVALUE;

	if (0 == strcmp(str, "ZBX_DC_FLAG_LLD"))
		return ZBX_DC_FLAG_LLD;

	if (0 == strcmp(str, "ZBX_DC_FLAG_UNDEF"))
		return ZBX_DC_FLAG_UNDEF;

	if (0 == strcmp(str, "ZBX_DC_FLAG_NOHISTORY"))
		return ZBX_DC_FLAG_NOHISTORY;

	if (0 == strcmp(str, "ZBX_DC_FLAG_NOTRENDS"))
		return ZBX_DC_FLAG_NOTRENDS;

	if (0 == strcmp(str, "ZBX_DC_FLAG_HASTRIGGER"))
		return ZBX_DC_FLAG_HASTRIGGER;

	fail_msg("Unknown history flag \"%s\"", str);

	return 0;
}

static void	zbx_dummy_history_sync(int *values_num, int *triggers_num, const zbx_events_funcs_t *events_cbs,
		zbx_ipc_async_socket_t *rtc, int config_history_storage_pipelines, int *more)
{
	ZBX_UNUSED(events_cbs);
	ZBX_UNUSED(rtc);
	ZBX_UNUSED(config_history_storage_pipelines);

	*values_num = 0;
	*triggers_num = 0;
	*more = 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Initializes a minimal, real history cache so that the fixed       *
 *          zbx_hc_is_itemid_cached_and_normal() can be exercised unwrapped.  *
 *                                                                            *
 ******************************************************************************/
static void	zbx_vcmock_ensure_history_cache(void)
{
	char		*error = NULL;
	zbx_uint64_t	trends_cache_size = 0;

	if (SUCCEED != zbx_init_database_cache(get_program_type, zbx_dummy_history_sync, ZBX_MEBIBYTE, ZBX_MEBIBYTE,
			&trends_cache_size, &error))
	{
		fail_msg("Cannot initialize history cache: %s", ZBX_NULL2EMPTY_STR(error));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: Pushes a history cache tail record for itemid through the         *
 *          production write path (zbx_dc_add_history_variant() +             *
 *          zbx_dc_flush_history()).                                          *
 *                                                                            *
 ******************************************************************************/
static void	zbx_vcmock_push_history_tail(zbx_uint64_t itemid, unsigned char value_type, zbx_timespec_t ts,
		int novalue, int notsupported)
{
	zbx_variant_t		value;
	zbx_pp_value_opt_t	value_opt;

	memset(&value_opt, 0, sizeof(value_opt));

	if (0 != notsupported)
		zbx_variant_set_error(&value, zbx_strdup(NULL, "mock unsupported item error"));
	else if (0 != novalue)
		zbx_variant_set_none(&value);
	else
		zbx_variant_set_ui64(&value, 1);

	zbx_dc_add_history_variant(itemid, value_type, 0, &value, ts, &value_opt);
	zbx_dc_flush_history();

	zbx_variant_clear(&value);
}

int	__wrap_zbx_dc_get_data_expected_from(zbx_uint64_t itemid, int *seconds);
int	__wrap_zbx_dc_get_proxy_nodata_win(zbx_uint64_t hostid, zbx_proxy_suppress_t *nodata_win, int *lastaccess);
void	__wrap_zbx_vps_monitor_add_collected(zbx_uint64_t values_num);

int	__wrap_zbx_dc_get_proxy_nodata_win(zbx_uint64_t hostid, zbx_proxy_suppress_t *nodata_win, int *lastaccess)
{
	ZBX_UNUSED(hostid);

	memset(nodata_win, 0, sizeof(zbx_proxy_suppress_t));
	nodata_win->flags = mock_proxy_flags;
	*lastaccess = zbx_vcmock_get_ts().sec - mock_proxy_lastaccess_age;

	return SUCCEED;
}

/* zbx_dc_flush_history()'s VPS (values-per-second) accounting step reads the separate DC              */
/* config cache singleton (get_dc_config()), which a lightweight history-cache-only test has no        */
/* reason to bootstrap - it is pure telemetry, not part of the nodata()/is_itemid_cached_and_normal()  */
/* decision logic under test here, so it is stubbed out rather than initializing a second subsystem.   */
void	__wrap_zbx_vps_monitor_add_collected(zbx_uint64_t values_num)
{
	ZBX_UNUSED(values_num);
}

/* evalfunc.c is linked as a single translation unit, so the symbols referenced by the other function */
/* evaluators it contains (baseline/trend/macro substitution) must resolve even though this suite     */
/* only ever exercises evaluate_NODATA() at runtime - stub them out rather than pulling in those      */
/* subsystems for real (returns FAIL, since none of this suite's cases exercise these paths).         */
int	__wrap_zbx_baseline_get_data(uint64_t itemid, unsigned char value_type, time_t now, const char *period,
		int season_num, zbx_time_unit_t season_unit, int skip, zbx_vector_dbl_t *values,
		zbx_vector_uint64_t *index, char **error);

void	__wrap_zbx_recalc_time_period(time_t *ts_from, int table_group);

int	__wrap_substitute_simple_macros(zbx_uint64_t *actionid, const zbx_db_event *event, const zbx_db_event *r_event,
		zbx_uint64_t *userid, const zbx_uint64_t *hostid, const zbx_dc_host_t *dc_host,
		const zbx_dc_item_t *dc_item, zbx_db_alert *alert, const zbx_db_acknowledge *ack,
		const zbx_service_alarm_t *service_alarm, const zbx_db_service *service, const char *tz, char **data,
		int macro_type, char *error, int maxerrlen);

int	__wrap_substitute_simple_macros(zbx_uint64_t *actionid, const zbx_db_event *event, const zbx_db_event *r_event,
		zbx_uint64_t *userid, const zbx_uint64_t *hostid, const zbx_dc_host_t *dc_host,
		const zbx_dc_item_t *dc_item, zbx_db_alert *alert, const zbx_db_acknowledge *ack,
		const zbx_service_alarm_t *service_alarm, const zbx_db_service *service, const char *tz, char **data,
		int macro_type, char *error, int maxerrlen)
{
	ZBX_UNUSED(actionid);
	ZBX_UNUSED(event);
	ZBX_UNUSED(r_event);
	ZBX_UNUSED(userid);
	ZBX_UNUSED(hostid);
	ZBX_UNUSED(dc_host);
	ZBX_UNUSED(dc_item);
	ZBX_UNUSED(alert);
	ZBX_UNUSED(ack);
	ZBX_UNUSED(service_alarm);
	ZBX_UNUSED(service);
	ZBX_UNUSED(tz);
	ZBX_UNUSED(data);
	ZBX_UNUSED(macro_type);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	return SUCCEED;
}

int	__wrap_zbx_dc_get_data_expected_from(zbx_uint64_t itemid, int *seconds)
{
	ZBX_UNUSED(itemid);
	*seconds = zbx_vcmock_get_ts().sec - 600;

	return SUCCEED;
}

int	__wrap_zbx_baseline_get_data(uint64_t itemid, unsigned char value_type, time_t now, const char *period,
		int season_num, zbx_time_unit_t season_unit, int skip, zbx_vector_dbl_t *baseline_get_data_values,
		zbx_vector_uint64_t *baseline_get_data_index, char **error)
{
	ZBX_UNUSED(itemid);
	ZBX_UNUSED(value_type);
	ZBX_UNUSED(now);
	ZBX_UNUSED(period);
	ZBX_UNUSED(season_num);
	ZBX_UNUSED(season_unit);
	ZBX_UNUSED(skip);
	ZBX_UNUSED(baseline_get_data_values);
	ZBX_UNUSED(baseline_get_data_index);
	ZBX_UNUSED(error);

	return FAIL;
}

void	__wrap_zbx_recalc_time_period(time_t *ts_from, int table_group)
{
	ZBX_UNUSED(table_group);
	ZBX_UNUSED(ts_from);
}

void	zbx_mock_test_entry(void **state)
{
	int			err, expected_ret, returned_ret;
	char			*error = NULL;
	const char		*params;
	zbx_dc_item_t		item;
	zbx_vcmock_ds_item_t	*ds_item;
	zbx_timespec_t		ts;
	zbx_mock_handle_t	handle;
	zbx_variant_t		returned_value;
	zbx_dc_evaluate_item_t	evaluate_item;

	ZBX_UNUSED(state);

	zbx_update_epsilon_to_float_precision();

	err = zbx_vc_init(get_zbx_config_value_cache_size(), &error);
	zbx_mock_assert_result_eq("Value cache initialization failed", SUCCEED, err);

	zbx_vc_enable();

	zbx_vcmock_ds_init();

	memset(&item, 0, sizeof(zbx_dc_item_t));

	ds_item = zbx_vcmock_ds_first_item();
	item.itemid = ds_item->itemid;
	item.value_type = ds_item->value_type;

	params = zbx_mock_get_parameter_string("in.params");

	handle = zbx_mock_get_parameter_handle("in");
	zbx_vcmock_set_time(handle, "time");
	ts = zbx_vcmock_get_ts();

	mock_proxy_flags = get_optional_flag_list("in.proxy_flags", str_to_proxy_suppress_flag);
	mock_proxy_lastaccess_age = get_optional_parameter_int("in.proxy_lastaccess_age", 0);

	if (0 != get_optional_parameter_int("in.proxyid", 0))
	{
		unsigned char	tail_flags;

		/* The real zbx_hc_is_itemid_cached_and_normal() always dereferences the history cache */
		/* singleton, so it must exist even for the "nothing cached for this item" scenario.   */
		zbx_vcmock_ensure_history_cache();

		if (0 != get_optional_parameter_int("in.tail_exists", 0))
		{
			zbx_timespec_t	tail_ts = ts;

			tail_flags = get_optional_flag_list("in.tail_flags", str_to_dc_flag);
			tail_ts.sec -= get_optional_parameter_int("in.tail_ts_offset", 0);
			zbx_vcmock_push_history_tail(item.itemid, item.value_type, tail_ts,
					0 != (tail_flags & ZBX_DC_FLAG_NOVALUE),
					ITEM_STATE_NOTSUPPORTED ==
							get_optional_item_state("in.tail_state",
									ITEM_STATE_NORMAL));
		}
	}

	evaluate_item.itemid = item.itemid;
	evaluate_item.value_type = item.value_type;
	evaluate_item.proxyid = (0 != get_optional_parameter_int("in.proxyid", 0) ? 1 : item.host.proxyid);
	evaluate_item.host = item.host.host;
	evaluate_item.key_orig = item.key_orig;

	returned_ret = evaluate_function(&returned_value, &evaluate_item, "nodata", params, &ts, &error);

	zbx_vc_flush_stats();

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	zbx_mock_assert_result_eq("return value", expected_ret, returned_ret);

	if (SUCCEED == expected_ret)
	{
		const char	*expected_value;

		handle = zbx_mock_get_parameter_handle("out.value");

		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string_ex(handle, &expected_value)))
			fail_msg("Cannot read output value: %s", zbx_mock_error_string(err));

		if (ZBX_VARIANT_DBL != returned_value.type)
		{
			fail_msg("function result '%s' has unexpected type '%s'",
					zbx_variant_value_desc(&returned_value),
					zbx_variant_type_desc(&returned_value));
		}

		zbx_mock_assert_double_eq("function result", atof(expected_value), returned_value.data.dbl);
	}
	else
	{
		check_expected_error(error);
	}

	zbx_free(error);

	if (SUCCEED == returned_ret)
		zbx_variant_clear(&returned_value);

	zbx_vcmock_ds_destroy();
}
