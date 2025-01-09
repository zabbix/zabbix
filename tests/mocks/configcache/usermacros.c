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
#include "zbxmockutil.h"
#include "dbconfig.h"
#include "configcache.h"
#include "configcache_mock.h"

void	mock_config_free_user_macros(void);

static int	mock_um_macro_compare(const void *d1, const void *d2)
{
	const zbx_um_macro_t	*m1 = *(const zbx_um_macro_t * const *)d1;
	const zbx_um_macro_t	*m2 = *(const zbx_um_macro_t * const *)d2;
	int		ret;

	if (0 != (ret = strcmp(m1->name, m2->name)))
		return ret;

	/* ZBX_CONDITION_OPERATOR_EQUAL (0) has higher priority than ZBX_CONDITION_OPERATOR_REGEXP (8) */
	ZBX_RETURN_IF_NOT_EQUAL(m1->context_op, m2->context_op);

	return zbx_strcmp_null(m1->context, m2->context);
}


void	mock_config_load_user_macros(const char *path)
{
	zbx_mock_handle_t	hmacros, handle;
	zbx_mock_error_t	err;
	int			i, index = 0;
	zbx_mock_config_t	*mock_config = get_mock_config();

	zbx_vector_um_host_create(&(mock_config->um_hosts));

	hmacros = zbx_mock_get_parameter_handle(path);
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hmacros, &handle))))
	{
		zbx_um_host_t		*host, host_local;
		zbx_um_macro_t		*macro;
		char			*name = NULL, *context = NULL;
		const char		*macro_name, *macro_value;
		zbx_mock_handle_t	hhostid;

		index++;

		if (ZBX_MOCK_SUCCESS != err)
			fail_msg("Cannot read 'macros' element #%d: %s", index, zbx_mock_error_string(err));

		if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(handle, "hostid", &hhostid))
		{
			if (ZBX_MOCK_SUCCESS != zbx_mock_uint64(hhostid, &host_local.hostid))
				fail_msg("Cannot parse macro hostid");
		}
		else
			host_local.hostid = 0;

		if (FAIL == (i = zbx_vector_um_host_search(&(mock_config->um_hosts), &host_local,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			host = (zbx_um_host_t *)zbx_malloc(NULL, sizeof(zbx_um_host_t));
			host->hostid = host_local.hostid;
			host->refcount = 0;
			zbx_vector_um_macro_create(&host->macros);
			zbx_vector_uint64_create(&host->templateids);

			zbx_vector_um_host_append(&(mock_config->um_hosts), host);
		}
		else
			host = mock_config->um_hosts.values[i];

		macro_name = zbx_mock_get_object_member_string(handle, "name");
		macro_value = zbx_mock_get_object_member_string(handle, "value");

		if (SUCCEED != zbx_user_macro_parse_dyn(macro_name, &name, &context, NULL, NULL))
			fail_msg("invalid user macro: %s", macro_name);

		macro = (zbx_um_macro_t *)zbx_malloc(NULL, sizeof(zbx_um_macro_t));
		macro->hostid = host_local.hostid;
		macro->name = name;
		macro->value = zbx_strdup(NULL, macro_value);
		macro->context_op = 0;
		macro->type = 0;
		macro->context = context;
		macro->refcount = 0;

		zbx_vector_um_macro_append(&host->macros, macro);
	}

	for (i = 0; i < mock_config->um_hosts.values_num; i++)
		zbx_vector_um_macro_sort(&(mock_config->um_hosts.values[i]->macros), mock_um_macro_compare);

	mock_config->dc.um_cache = (zbx_um_cache_t *)zbx_malloc(NULL, sizeof(zbx_um_cache_t));
	memset (mock_config->dc.um_cache, 0, sizeof(zbx_um_cache_t));
	mock_config->dc.um_cache->refcount = 10000;

	mock_config->initialized |= ZBX_MOCK_CONFIG_USERMACROS;
}

static void	mock_str_free(char *str)
{
	zbx_free(str);
}

static void	mock_um_macro_free(zbx_um_macro_t *macro)
{
	mock_str_free((char *)macro->name);
	mock_str_free((char *)macro->context);
	mock_str_free((char *)macro->value);
	zbx_free(macro);
}

static void	mock_um_host_free(zbx_um_host_t *host)
{
	zbx_vector_um_macro_clear_ext(&host->macros, mock_um_macro_free);
	zbx_vector_um_macro_destroy(&host->macros);

	zbx_vector_uint64_destroy(&host->templateids);
	zbx_free(host);
}


void	mock_config_free_user_macros(void)
{
	zbx_vector_um_host_clear_ext(&(get_mock_config()->um_hosts), mock_um_host_free);
	zbx_vector_um_host_destroy(&(get_mock_config()->um_hosts));
}
