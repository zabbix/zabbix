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
#include "zbxmockdb.h"

#include "zbxalgo.h"
#include "zbxcachevalue.h"
#include "zbxvariant.h"
#include "zbxtime.h"
#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"
#include "zbxcalc.h"

#include "zbxnum.h"

#include "mocks/valuecache/valuecache_mock.h"
#include "../../../src/libs/zbxtrends/trends.h"

int	__wrap_zbx_substitute_macros_args(zbx_token_search_t search, char **data, char *error, size_t maxerrlen,
		zbx_macro_resolv_func_t resolver, va_list args);

int	__wrap_zbx_dc_get_data_expected_from(zbx_uint64_t itemid, int *seconds);

int	__wrap_zbx_baseline_get_data(uint64_t itemid, unsigned char value_type, time_t now, const char *period,
		int season_num, zbx_time_unit_t season_unit, int skip, zbx_vector_dbl_t *values,
		zbx_vector_uint64_t *index, char **error);

void	__wrap_zbx_recalc_time_period(time_t *ts_from, int table_group);

zbx_uint64_t	__wrap_zbx_history_get_trends_flags(void);

int	__wrap_zbx_substitute_macros_args(zbx_token_search_t search, char **data, char *error, size_t maxerrlen,
		zbx_macro_resolv_func_t resolver, va_list args)
{
	ZBX_UNUSED(search);
	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);
	ZBX_UNUSED(resolver);
	ZBX_UNUSED(args);

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
	ZBX_UNUSED(error);
	ZBX_UNUSED(skip);

	zbx_mock_extract_yaml_values_dbl(zbx_mock_get_parameter_handle("in.baseline_values"), baseline_get_data_values);
	zbx_mock_extract_yaml_values_uint64(zbx_mock_get_parameter_handle("in.baseline_index"),
			baseline_get_data_index);

	return SUCCEED;
}

void	__wrap_zbx_recalc_time_period(time_t *ts_from, int table_group)
{
	ZBX_UNUSED(table_group);
	ZBX_UNUSED(ts_from);
}

zbx_uint64_t	__wrap_zbx_history_get_trends_flags(void)
{
	return 0xFF;
}

static void	check_variant_equal(const zbx_variant_t *v, zbx_mock_handle_t expected_handle, char *path,
		size_t path_size)
{
	zbx_mock_error_t	err;
	const char		*expected_str;

	if (ZBX_VARIANT_VECTOR == v->type)
	{
		zbx_mock_handle_t	hvalue;
		int			i = 0;

		while (ZBX_MOCK_SUCCESS == (err = zbx_mock_vector_element(expected_handle, &hvalue)))
		{
			size_t	old_path_strlen;

			if (i >= v->data.vector->values_num)
				fail_msg("%s: Result vector has less elements than expected (i=%d)", path, i);

			old_path_strlen = strlen(path);
			zbx_snprintf(path + old_path_strlen, path_size - old_path_strlen, "[%d]", i);

			check_variant_equal(&v->data.vector->values[i], hvalue, path, path_size);

			path[old_path_strlen] = '\0';

			i++;
		}

		if (0 == i && ZBX_MOCK_NOT_A_VECTOR == err)
		{
			zbx_vector_str_t	expected_elems;
			zbx_vector_str_t	actual_elems;
			zbx_mock_handle_t	helem;

			/* check vector as unordered multi-set of strings */

			zbx_vector_str_create(&actual_elems);

			for (i = 0; i < v->data.vector->values_num; i++)
			{
				zbx_variant_t	*pelem = &v->data.vector->values[i];
				zbx_variant_t	vstr;

				zbx_variant_copy(&vstr, pelem);

				if (SUCCEED != zbx_variant_convert(&vstr, ZBX_VARIANT_STR))
				{
					fail_msg("%s: Failed to convert value of type %s to string: %s", path,
							zbx_variant_type_desc(pelem), zbx_variant_value_desc(pelem));
				}

				zbx_vector_str_append(&actual_elems, vstr.data.str);
			}

			if (ZBX_MOCK_SUCCESS != (err = zbx_mock_object_member(expected_handle, "strset", &hvalue)))
			{
				fail_msg("%s: Cannot read output value as an unordered multi-set of strings "
						"(resulting value: %s): %s",
						path, zbx_variant_value_desc(v), zbx_mock_error_string(err));
			}

			zbx_vector_str_create(&expected_elems);

			while (ZBX_MOCK_SUCCESS == (err = zbx_mock_vector_element(hvalue, &helem)))
			{
				const char	*str;

				if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string_ex(helem, &str)))
					break;

				zbx_vector_str_append(&expected_elems, (char *)str);
			}

			if (ZBX_MOCK_END_OF_VECTOR != err)
			{
				fail_msg("%s: Cannot read output value as an unordered multi-set of strings "
					"(resulting value: %s): %s", path,
					zbx_variant_value_desc(v), zbx_mock_error_string(err));
			}

			if (expected_elems.values_num != actual_elems.values_num)
			{
				fail_msg("%s: element count differs from expected (expected: %d): %d", path,
						expected_elems.values_num, actual_elems.values_num);
			}

			zbx_vector_str_sort(&actual_elems, ZBX_DEFAULT_STR_COMPARE_FUNC);
			zbx_vector_str_sort(&expected_elems, ZBX_DEFAULT_STR_COMPARE_FUNC);

			for (i = 0; i < actual_elems.values_num; i++)
			{
				size_t	old_path_strlen = strlen(path);
				zbx_snprintf(path + old_path_strlen, path_size - old_path_strlen, "[\"%s\"]",
						expected_elems.values[i]);

				zbx_mock_assert_str_eq(path, expected_elems.values[i], actual_elems.values[i]);

				path[old_path_strlen] = '\0';
			}

			zbx_vector_str_destroy(&expected_elems);
			zbx_vector_str_clear_ext(&actual_elems, zbx_str_free);
			zbx_vector_str_destroy(&actual_elems);

			return;
		}

		if (ZBX_MOCK_END_OF_VECTOR != err)
		{
			fail_msg("%s: Cannot read output value as vector (resulting value: %s): %s", path,
					zbx_variant_value_desc(v), zbx_mock_error_string(err));
		}

		if (i < v->data.vector->values_num)
		{
			fail_msg("%s: Result vector has more elements than expected (expected: %d, got: %d)", path, i,
					v->data.vector->values_num);
		}

		return;
	}

	if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string_ex(expected_handle, &expected_str)))
		fail_msg("%s: Cannot read output value: %s", path, zbx_mock_error_string(err));

	if (NULL == expected_str)
		fail_msg("%s: Read a NULL output value (result value: \"%s\")", path, zbx_variant_value_desc(v));

	switch (v->type)
	{
		case ZBX_VARIANT_DBL:
			zbx_mock_assert_double_eq(path, atof(expected_str),
					v->data.dbl);
			break;
		case ZBX_VARIANT_UI64:
		{
			zbx_uint64_t	expected_ui64;

			if (SUCCEED != zbx_is_uint64(expected_str, &expected_ui64))
			{
				fail_msg("%s: function result value '" ZBX_FS_UI64
						"' does not match expected result '%s'",
						path, v->data.ui64, expected_str);
			}
			zbx_mock_assert_uint64_eq(path, expected_ui64, v->data.ui64);
			break;
		}
		case ZBX_VARIANT_STR:
			zbx_mock_assert_str_eq(path, expected_str, v->data.str);
			break;
		default:
			fail_msg("%s: function result value '%s' has unexpected type '%s'",
					path, zbx_variant_value_desc(v), zbx_variant_type_desc(v));
			break;
	}
}

void	zbx_mock_test_entry(void **state)
{
	int			err, expected_ret, returned_ret;
	char			*error = NULL;
	const char		*function, *params;
	zbx_dc_item_t		item;
	zbx_vcmock_ds_item_t	*ds_item;
	zbx_timespec_t		ts;
	zbx_mock_handle_t	handle;
	zbx_variant_t		returned_value;
	zbx_dc_evaluate_item_t	evaluate_item;
	zbx_history_selector_t	selector = {0};
	char			path[MAX_STRING_LEN] = "result";

	ZBX_UNUSED(state);

	zbx_update_epsilon_to_float_precision();

	function = zbx_mock_get_parameter_string("in.function");
	params = zbx_mock_get_parameter_string("in.params");

#ifndef HAVE_LIBXML2
	if (0 == strcmp(function, "xmlxpath"))
		skip();
#endif

	err = zbx_vc_init(get_zbx_config_value_cache_size(), &error);
	zbx_mock_assert_result_eq("Value cache initialization failed", SUCCEED, err);

	zbx_vc_enable();

	zbx_vcmock_ds_init();

	zbx_mockdb_init();

	memset(&item, 0, sizeof(zbx_dc_item_t));

	ds_item = zbx_vcmock_ds_first_item();
	item.itemid = ds_item->itemid;
	item.value_type = ds_item->value_type;

	handle = zbx_mock_get_parameter_handle("in");
	zbx_vcmock_set_time(handle, "time");
	ts = zbx_vcmock_get_ts();

	evaluate_item.itemid = item.itemid;
	evaluate_item.value_type = item.value_type;
	evaluate_item.proxyid = item.host.proxyid;
	evaluate_item.host = item.host.host;
	evaluate_item.key_orig = item.key_orig;

	if (SUCCEED != (returned_ret = zbx_evaluate_function(&returned_value, &evaluate_item, function, params, &ts,
			&selector, &error)))
	{
		printf("zbx_evaluate_function returned error: %s\n", error);
		zbx_free(error);
	}

	zbx_vc_flush_stats();

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	zbx_mock_assert_result_eq("return value", expected_ret, returned_ret);

	if (SUCCEED == expected_ret)
	{
		handle = zbx_mock_get_parameter_handle("out.value");

		check_variant_equal(&returned_value, handle, path, sizeof(path));
	}
	if (SUCCEED == returned_ret)
		zbx_variant_clear(&returned_value);

	zbx_vcmock_ds_destroy();

	zbx_mockdb_destroy();
}
