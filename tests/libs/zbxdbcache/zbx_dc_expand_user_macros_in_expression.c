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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxserver.h"
#include "common.h"
#include "zbxalgo.h"
#include "dbcache.h"
#include "mutexs.h"
#define ZBX_DBCONFIG_IMPL
#include "dbconfig.h"

static zbx_vector_ptr_t	macros;

void	*__wrap_zbx_hashset_search(zbx_hashset_t *hs, const void *data)
{
	int			i;
	const ZBX_DC_GMACRO_M	*query = (const ZBX_DC_GMACRO_M *)data;

	if (hs != &config->gmacros_m)
		return NULL;

	for (i = 0; i < macros.values_num; i++)
	{
		ZBX_DC_GMACRO_M		*gm = (ZBX_DC_GMACRO_M *)macros.values[i];
		if (0 == strcmp(gm->macro, query->macro))
			return gm;
	}

	return NULL;
}

static int	config_gmacro_context_compare(const void *d1, const void *d2)
{
	const ZBX_DC_GMACRO	*m1 = *(const ZBX_DC_GMACRO **)d1;
	const ZBX_DC_GMACRO	*m2 = *(const ZBX_DC_GMACRO **)d2;

	/* macros without context have higher priority than macros with */
	if (NULL == m1->context)
		return NULL == m2->context ? 0 : -1;

	if (NULL == m2->context)
		return 1;

	/* CONDITION_OPERATOR_EQUAL (0) has higher priority than CONDITION_OPERATOR_REGEXP (8) */
	ZBX_RETURN_IF_NOT_EQUAL(m1->context_op, m2->context_op);

	return strcmp(m1->context, m2->context);
}


static void	init_macros(const char *path)
{
	zbx_mock_handle_t	hmacros, handle;
	zbx_mock_error_t	err;
	const char		*macro_name, *macro_value;
	int			i;
	ZBX_DC_GMACRO_M		*gm;
	ZBX_DC_GMACRO		*macro;

	zbx_vector_ptr_create(&macros);

	hmacros = zbx_mock_get_parameter_handle(path);
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hmacros, &handle))))
	{
		char	*name = NULL, *context = NULL;

		if (ZBX_MOCK_SUCCESS != err)
		{
			fail_msg("Cannot read 'macros' element #%d: %s", macros.values_num,
					zbx_mock_error_string(err));
		}

		macro_name = zbx_mock_get_object_member_string(handle, "name");
		macro_value = zbx_mock_get_object_member_string(handle, "value");

		if (SUCCEED != zbx_user_macro_parse_dyn(macro_name, &name, &context, NULL, NULL))
			fail_msg("invalid user macro: %s", macro_name);

		for (i = 0; i < macros.values_num; i++)
		{
			gm = (ZBX_DC_GMACRO_M *)macros.values[i];
			if (0 == strcmp(gm->macro, name))
				break;
		}

		if (i == macros.values_num)
		{
			gm = zbx_malloc(NULL, sizeof(ZBX_DC_GMACRO_M));
			gm->macro = zbx_strdup(NULL, name);
			zbx_vector_ptr_create(&gm->gmacros);
			zbx_vector_ptr_append(&macros, gm);
		}

		macro = (ZBX_DC_GMACRO *)zbx_malloc(0, sizeof(ZBX_DC_GMACRO));
		memset(macro, 0, sizeof(ZBX_DC_GMACRO));
		macro->macro = name;
		macro->context = context;
		macro->value = macro_value;
		zbx_vector_ptr_append(&gm->gmacros, macro);
	}

	for (i = 0; i < macros.values_num; i++)
	{
		gm = (ZBX_DC_GMACRO_M *)macros.values[i];
		zbx_vector_ptr_sort(&gm->gmacros, config_gmacro_context_compare);
	}
}

static void	free_string(const char *str)
{
	char	*ptr = (char *)str;
	zbx_free(ptr);
}

static void	free_macros()
{
	ZBX_DC_GMACRO_M	*gm;
	ZBX_DC_GMACRO	*macro;
	int		i, j;

	for (i = 0; i < macros.values_num; i++)
	{
		gm = (ZBX_DC_GMACRO_M *)macros.values[i];
		for (j = 0; j < gm->gmacros.values_num; j++)
		{
			macro = (ZBX_DC_GMACRO *)gm->gmacros.values[j];
			free_string(macro->macro);
			free_string(macro->context);
			zbx_free(macro);
		}
		zbx_vector_ptr_destroy(&gm->gmacros);
		free_string(gm->macro);
		zbx_free(gm);
	}

	zbx_vector_ptr_destroy(&macros);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mock_test_entry                                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_mock_test_entry(void **state)
{
	char		*returned_expression;
	const char	*expression, *expected_expression;
	ZBX_DC_CONFIG	dc_config = {0};

	ZBX_UNUSED(state);

	config = &dc_config;
	init_macros("in.macros");

	expression = zbx_mock_get_parameter_string("in.expression");
	expected_expression = zbx_mock_get_parameter_string("out.expression");

	/* the macro expansion relies on wrapped zbx_hashset_search which returns mocked */
	/* macros when used with global macro index hashset                              */
	returned_expression = zbx_dc_expand_user_macros_in_expression(expression, NULL, 0);
	zbx_mock_assert_str_eq("Expanded expression", expected_expression, returned_expression);

	zbx_free(returned_expression);

	free_macros();
}
