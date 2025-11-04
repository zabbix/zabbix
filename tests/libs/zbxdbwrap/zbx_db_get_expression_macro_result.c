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

#include "zbxdbwrap.h"
#include "zbxdbhigh.h"
#include "zbx_expression_constants.h"

#include "zbxmocktest.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

zbx_vector_uint64_t	test_hostids;

zbx_dc_um_handle_t	*__wrap_zbx_dc_open_user_macros(void);

void	__wrap_zbx_dc_close_user_macros(zbx_dc_um_handle_t *um_handle);
int	__wrap_zbx_db_trigger_get_all_hostids(const zbx_db_trigger *trigger, const zbx_vector_uint64_t **hostids);

void	__wrap_zbx_dc_get_user_macro(const zbx_dc_um_handle_t *um_handle, const char *macro,
		const zbx_uint64_t *hostids, int hostids_num, char **value);

int	__wrap_zbx_db_with_trigger_itemid(const zbx_db_trigger *trigger, char **replace_to, int N_functionid,
		zbx_db_with_itemid_func_t cb, int request);

zbx_dc_um_handle_t	*__wrap_zbx_dc_open_user_macros(void)
{
	return NULL;
}

void	__wrap_zbx_dc_close_user_macros(zbx_dc_um_handle_t *um_handle)
{
	ZBX_UNUSED(um_handle);
}

int	__wrap_zbx_db_trigger_get_all_hostids(const zbx_db_trigger *trigger, const zbx_vector_uint64_t **hostids)
{
	ZBX_UNUSED(trigger);
	*hostids = &test_hostids;

	return SUCCEED;
}

void	__wrap_zbx_dc_get_user_macro(const zbx_dc_um_handle_t *um_handle, const char *macro,
		const zbx_uint64_t *hostids, int hostids_num, char **value)
{
	ZBX_UNUSED(um_handle);
	ZBX_UNUSED(macro);
	ZBX_UNUSED(hostids);
	ZBX_UNUSED(hostids_num);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() macro:%s", __func__, macro);

	const char *macro_name = zbx_mock_get_optional_parameter_string("in.macro");

	if (NULL != macro_name && 0 != strcmp(macro, macro_name))
	{
		zbx_mock_assert_str_eq("expected macro", macro_name, macro);
	}

	*value = zbx_strdup(NULL, zbx_mock_get_parameter_string("in.macro_value"));
}

int	__wrap_zbx_db_with_trigger_itemid(const zbx_db_trigger *trigger, char **replace_to, int N_functionid,
		zbx_db_with_itemid_func_t cb, int request)
{
	ZBX_UNUSED(trigger);
	ZBX_UNUSED(N_functionid);
	ZBX_UNUSED(cb);
	ZBX_UNUSED(request);

	*replace_to = zbx_strdup(NULL, zbx_mock_get_parameter_string("in.macro_value"));

	return SUCCEED;
}

static int	expression_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_to, char **data, char *error,
		size_t maxerrlen)
{
	int	ret = SUCCEED;

	/* Passed arguments */
	zbx_dc_um_handle_t	*um_handle = va_arg(args, zbx_dc_um_handle_t *);
	const zbx_db_event	*event = va_arg(args, const zbx_db_event *);

	/* Passed arguments holding cached data */
	const zbx_vector_uint64_t	**trigger_hosts = va_arg(args, const zbx_vector_uint64_t **);

	if (EVENT_SOURCE_TRIGGERS == event->source)
	{
		if (SUCCEED == zbx_token_is_user_macro(p->macro, &p->token))
		{
			if (SUCCEED == zbx_db_trigger_get_all_hostids(&event->trigger, trigger_hosts))
			{
				zbx_dc_get_user_macro(um_handle, p->macro, (*trigger_hosts)->values,
						(*trigger_hosts)->values_num, replace_to);
			}

			p->pos = p->token.loc.r;
		}
		else if (ZBX_TOKEN_EXPRESSION_MACRO == p->inner_token.type)
		{
			zbx_timespec_t	ts;
			char		*errmsg = NULL;

			zbx_timespec(&ts);

			if (SUCCEED != (ret = zbx_db_get_expression_macro_result(event, *data,
					&p->inner_token.data.expression_macro.expression, &ts, replace_to, &errmsg)))
			{
				*errmsg = tolower(*errmsg);
				zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot evaluate expression macro: %s", __func__,
						errmsg);
				zbx_strlcpy(error, errmsg, maxerrlen);
				zbx_free(errmsg);
			}
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_HOST) || 0 == strcmp(p->macro, MVAR_HOSTNAME))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_to, p->index, &zbx_dc_get_host_value,
					ZBX_DC_REQUEST_HOST_HOST);
		}
	}

	return ret;
}

void	zbx_mock_test_entry(void **state)
{
	int			expected_ret, returned_ret;
	char			*expression;
	const char		*expected_expression;
	zbx_db_event		event;
	zbx_dc_um_handle_t	*um_handle;

	const zbx_vector_uint64_t	*trigger_hosts = NULL;

	ZBX_UNUSED(state);

	event.source = EVENT_SOURCE_TRIGGERS;
	expression = zbx_strdup(NULL, zbx_mock_get_parameter_string("in.expression"));
	expected_expression = zbx_mock_get_parameter_string("out.expression");
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	um_handle = zbx_dc_open_user_macros();

	returned_ret = zbx_substitute_macros_ext_search(ZBX_TOKEN_SEARCH_EXPRESSION_MACRO, &expression, NULL, 0,
			&expression_resolv, um_handle, &event, &trigger_hosts);

	zbx_dc_close_user_macros(um_handle);

	zbx_mock_assert_result_eq("return value", expected_ret, returned_ret);
	zbx_mock_assert_str_eq("resulting expression", expected_expression, expression);

	zbx_free(expression);
}
