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
#include "zbxmockassert.h"
#include "zbxmockutil.h"
#include "zbxmockjson.h"
#include "zbxembed.h"

#include "../../../src/zabbix_server/preprocessor/item_preproc.h"
#include "../../../src/zabbix_server/preprocessor/preproc_history.h"

#include "trapper_preproc_test_run.h"

zbx_es_t	es_engine;

int	__wrap_zbx_preprocessor_test(unsigned char value_type, const char *value, const zbx_timespec_t *ts,
		const zbx_vector_ptr_t *steps, zbx_vector_ptr_t *results, zbx_vector_ptr_t *history,
		char **preproc_error, char **error);

int	__wrap_DBget_user_by_active_session(const char *sessionid, zbx_user_t *user);
int	__wrap_DBget_user_by_auth_token(const char *formatted_auth_token_hash, zbx_user_t *user);
void	__wrap_zbx_user_init(zbx_user_t *user);
void	__wrap_zbx_user_free(zbx_user_t *user);
void	__wrap_init_result(AGENT_RESULT *result);
void	__wrap_free_result(AGENT_RESULT *result);

int	__wrap_zbx_preprocessor_test(unsigned char value_type, const char *value, const zbx_timespec_t *ts,
		const zbx_vector_ptr_t *steps, zbx_vector_ptr_t *results, zbx_vector_ptr_t *history,
		char **preproc_error, char **error)
{
	int			i, results_num;
	zbx_preproc_op_t	*steps_array;
	zbx_preproc_result_t	*results_array, *result;
	zbx_vector_ptr_t	history_out;
	zbx_variant_t		value_var;

	ZBX_UNUSED(error);

	zbx_vector_ptr_create(&history_out);
	zbx_variant_set_str(&value_var, zbx_strdup(NULL, value));

	steps_array = (zbx_preproc_op_t *)zbx_malloc(NULL, steps->values_num * sizeof(zbx_preproc_op_t));
	for (i = 0; i < steps->values_num; i++)
		steps_array[i] = *(zbx_preproc_op_t *)steps->values[i];

	results_array = (zbx_preproc_result_t *)zbx_malloc(NULL, sizeof(zbx_preproc_result_t) * steps->values_num);
	memset(results_array, 0, sizeof(zbx_preproc_result_t) * steps->values_num);

	zbx_item_preproc_test(value_type, &value_var, ts, steps_array, steps->values_num, history, &history_out,
			results_array, &results_num, preproc_error);

	/* copy output history */
	zbx_vector_ptr_clear_ext(history, (zbx_clean_func_t)zbx_preproc_op_history_free);

	if (0 != history_out.values_num)
		zbx_vector_ptr_append_array(history, history_out.values, history_out.values_num);

	/* copy results */
	for (i = 0; i < results_num; i++)
	{
		result = (zbx_preproc_result_t *)zbx_malloc(NULL, sizeof(zbx_preproc_result_t));
		*result = results_array[i];
		zbx_vector_ptr_append(results, result);
	}

	zbx_variant_clear(&value_var);
	zbx_free(steps_array);
	zbx_free(results_array);
	zbx_vector_ptr_destroy(&history_out);

	return SUCCEED;
}

int	__wrap_DBget_user_by_active_session(const char *sessionid, zbx_user_t *user)
{
	ZBX_UNUSED(sessionid);

	user->type = USER_TYPE_ZABBIX_ADMIN;
	user->userid = 0;

	return SUCCEED;
}

int	__wrap_DBget_user_by_auth_token(const char *formatted_auth_token_hash, zbx_user_t *user)
{
	ZBX_UNUSED(formatted_auth_token_hash);

	user->type = USER_TYPE_ZABBIX_ADMIN;
	user->userid = 0;

	return SUCCEED;
}

void	__wrap_zbx_user_init(zbx_user_t *user)
{
	user->username = NULL;
}

void	__wrap_zbx_user_free(zbx_user_t *user)
{
	zbx_free(user->username);
}

void	__wrap_init_result(AGENT_RESULT *result)
{
	ZBX_UNUSED(result);
}

void	__wrap_free_result(AGENT_RESULT *result)
{
	ZBX_UNUSED(result);
}


void	zbx_mock_test_entry(void **state)
{
	const char		*request;
	char			*error = NULL;
	struct zbx_json_parse	jp;
	struct zbx_json		out;
	int			returned_ret, expected_ret;

	ZBX_UNUSED(state);

	zbx_json_init(&out, 1024);

	request = zbx_mock_get_parameter_string("in.request");
	if (FAIL == zbx_json_open(request, &jp))
		fail_msg("Invalid request format: %s", zbx_json_strerror());

	returned_ret = zbx_trapper_preproc_test_run(&jp, &out, &error);
	if (FAIL == returned_ret)
		printf("zbx_trapper_preproc_test_run error: %s\n", error);
	else
		printf("zbx_trapper_preproc_test_run output: %s\n", out.buffer);

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	zbx_mock_assert_result_eq("Return value", expected_ret, returned_ret);

	if (FAIL == returned_ret)
		zbx_mock_assert_ptr_ne("Error pointer", NULL, error);
	else
		zbx_mock_assert_json_eq("Output", zbx_mock_get_parameter_string("out.response"), out.buffer);

	zbx_free(error);
	zbx_json_free(&out);
}
