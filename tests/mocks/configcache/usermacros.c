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
#include "zbxmockutil.h"
#include "dbconfig.h"
#include "configcache.h"
#include "configcache_mock.h"

extern zbx_mock_config_t	mock_config;

void	mock_config_free_user_macros(void);

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

static int	config_hmacro_context_compare(const void *d1, const void *d2)
{
	const ZBX_DC_HMACRO	*m1 = *(const ZBX_DC_HMACRO **)d1;
	const ZBX_DC_HMACRO	*m2 = *(const ZBX_DC_HMACRO **)d2;

	/* macros without context have higher priority than macros with */
	if (NULL == m1->context)
		return NULL == m2->context ? 0 : -1;

	if (NULL == m2->context)
		return 1;

	/* CONDITION_OPERATOR_EQUAL (0) has higher priority than CONDITION_OPERATOR_REGEXP (8) */
	ZBX_RETURN_IF_NOT_EQUAL(m1->context_op, m2->context_op);

	return strcmp(m1->context, m2->context);
}

void	mock_config_load_user_macros(const char *path)
{
	zbx_mock_handle_t	hmacros, handle;
	zbx_mock_error_t	err;
	int			i;
	ZBX_DC_HMACRO_HM	*hm;
	ZBX_DC_GMACRO_M		*gm;

	zbx_vector_ptr_create(&mock_config.host_macros);
	zbx_vector_ptr_create(&mock_config.global_macros);

	hmacros = zbx_mock_get_parameter_handle(path);
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hmacros, &handle))))
	{
		char			*name = NULL, *context = NULL;
		const char		*macro_name, *macro_value;
		zbx_uint64_t		macro_hostid;
		zbx_mock_handle_t	hhostid;

		if (ZBX_MOCK_SUCCESS != err)
		{
			fail_msg("Cannot read 'macros' element #%d: %s", mock_config.host_macros.values_num,
					zbx_mock_error_string(err));
		}

		if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(handle, "hostid", &hhostid))
		{
			if (ZBX_MOCK_SUCCESS != zbx_mock_uint64(hhostid, &macro_hostid))
				fail_msg("Cannot parse macro hostid");
			macro_hostid = zbx_mock_get_object_member_uint64(handle, "hostid");
		}
		else
			macro_hostid = 0;

		macro_name = zbx_mock_get_object_member_string(handle, "name");
		macro_value = zbx_mock_get_object_member_string(handle, "value");

		if (SUCCEED != zbx_user_macro_parse_dyn(macro_name, &name, &context, NULL, NULL))
			fail_msg("invalid user macro: %s", macro_name);

		if (0 == macro_hostid)
		{
			ZBX_DC_GMACRO	*macro;

			for (i = 0; i < mock_config.global_macros.values_num; i++)
			{
				gm = (ZBX_DC_GMACRO_M *)mock_config.global_macros.values[i];
				if (0 == strcmp(gm->macro, name))
					break;
			}

			if (i == mock_config.global_macros.values_num)
			{
				gm = zbx_malloc(NULL, sizeof(ZBX_DC_GMACRO_M));
				gm->macro = zbx_strdup(NULL, name);
				zbx_vector_ptr_create(&gm->gmacros);
				zbx_vector_ptr_append(&mock_config.global_macros, gm);
			}

			macro = (ZBX_DC_GMACRO *)zbx_malloc(0, sizeof(ZBX_DC_GMACRO));
			memset(macro, 0, sizeof(ZBX_DC_GMACRO));
			macro->macro = name;
			macro->context = context;
			macro->value = macro_value;
			zbx_vector_ptr_append(&gm->gmacros, macro);
		}
		else
		{
			ZBX_DC_HMACRO	*macro;

			for (i = 0; i < mock_config.host_macros.values_num; i++)
			{
				hm = (ZBX_DC_HMACRO_HM *)mock_config.host_macros.values[i];
				if (hm->hostid == macro_hostid && 0 == strcmp(hm->macro, name))
					break;
			}

			if (i == mock_config.host_macros.values_num)
			{
				hm = zbx_malloc(NULL, sizeof(ZBX_DC_HMACRO_HM));
				hm->hostid = macro_hostid;
				hm->macro = zbx_strdup(NULL, name);
				zbx_vector_ptr_create(&hm->hmacros);
				zbx_vector_ptr_append(&mock_config.host_macros, hm);
			}

			macro = (ZBX_DC_HMACRO *)zbx_malloc(0, sizeof(ZBX_DC_HMACRO));
			memset(macro, 0, sizeof(ZBX_DC_HMACRO));
			macro->macro = name;
			macro->context = context;
			macro->value = macro_value;
			zbx_vector_ptr_append(&hm->hmacros, macro);
		}
	}

	for (i = 0; i < mock_config.global_macros.values_num; i++)
	{
		gm = (ZBX_DC_GMACRO_M *)mock_config.global_macros.values[i];
		zbx_vector_ptr_sort(&gm->gmacros, config_gmacro_context_compare);
	}

	for (i = 0; i < mock_config.host_macros.values_num; i++)
	{
		hm = (ZBX_DC_HMACRO_HM *)mock_config.host_macros.values[i];
		zbx_vector_ptr_sort(&hm->hmacros, config_hmacro_context_compare);
	}

	mock_config.initialized |= ZBX_MOCK_CONFIG_USERMACROS;
}

void	mock_config_free_user_macros(void)
{
	int	i, j;

	for (i = 0; i < mock_config.global_macros.values_num; i++)
	{
		ZBX_DC_GMACRO_M	*gm;
		ZBX_DC_GMACRO	*macro;

		gm = (ZBX_DC_GMACRO_M *)mock_config.global_macros.values[i];
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
	zbx_vector_ptr_destroy(&mock_config.global_macros);

	for (i = 0; i < mock_config.host_macros.values_num; i++)
	{
		ZBX_DC_HMACRO_HM	*hm;
		ZBX_DC_HMACRO		*macro;

		hm = (ZBX_DC_HMACRO_HM *)mock_config.host_macros.values[i];
		for (j = 0; j < hm->hmacros.values_num; j++)
		{
			macro = (ZBX_DC_HMACRO *)hm->hmacros.values[j];
			free_string(macro->macro);
			free_string(macro->context);
			zbx_free(macro);
		}
		zbx_vector_ptr_destroy(&hm->hmacros);
		free_string(hm->macro);
		zbx_free(hm);
	}
	zbx_vector_ptr_destroy(&mock_config.host_macros);
}
