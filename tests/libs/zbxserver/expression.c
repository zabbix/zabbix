/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

#include "common.h"
#include "db.h"
#include "log.h"
#include "sysinfo.h"
#include "zbxserver.h"

#include "zbxmocktest.h"
#include "zbxmockdata.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	param_handle;

	char			actual_error[256];
	zbx_uint64_t		index = 0;
	const char		*expected_result = NULL, *expression = NULL;
	char			*actual_result = NULL, *out = NULL;
	size_t			res_len;
	int			i;
	zbx_token_reference_t	*token = NULL;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("expression", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &expression)))
	{
		fail_msg("Cannot get input 'expression' from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("index", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_uint64(param_handle, &index)))
	{
		fail_msg("Cannot get input 'index' from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("return", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &expected_result)))
	{
		fail_msg("Cannot get expected 'return' parameter from test case data: %s",
				zbx_mock_error_string(error));
	}

	token = zbx_malloc(NULL, sizeof(zbx_token_reference_t));
	*token = (zbx_token_reference_t){.index = index};
	get_trigger_expression_constant(expression, token, &out, &res_len);

	if (NULL != out)
	{
		actual_result = zbx_malloc(NULL, res_len + 1);

		for (i = 0; i < res_len; ++i)
			actual_result[i] = out[i];

		actual_result[res_len] = '\0';
		zbx_free(out);
	}

	if (0 != strcmp(expected_result, actual_result))
	{
		fail_msg("Got ->%s<- instead of ->%s<- as a result.", actual_result,
				expected_result);
	}

	zbx_free(actual_result);
	zbx_free(token);
}
