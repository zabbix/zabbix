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

#include "zbxexpr.h"
#include "zbxcacheconfig.h"
#include "zbx_expression_constants.h"

/* Internal header */
#include "zbxcacheconfig/user_macro.h"

#include "../zbxcacheconfig/um_cache_mock.h"

static int	macro_resolv_func(zbx_macro_resolv_data_t *p, va_list args, char **replace_with, char **data,
		char *error, size_t maxerrlen)
{
	/* Passed arguments */
	uint64_t			hostid = va_arg(args, uint64_t);
	const zbx_dc_um_handle_t	*um_handle = va_arg(args, const zbx_dc_um_handle_t *);

	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	if (ZBX_TOKEN_USER_MACRO == p->token.type || (ZBX_TOKEN_USER_FUNC_MACRO == p->token.type &&
				0 == strncmp(p->macro, MVAR_USER_MACRO, ZBX_CONST_STRLEN(MVAR_USER_MACRO))))
	{
		zbx_dc_get_user_macro(um_handle, p->macro, &hostid, 1, replace_with);
		p->pos = p->token.loc.r;
	}

	return SUCCEED;
}


static char	*mock_get_vault(zbx_mock_handle_t hvault)
{
	zbx_mock_handle_t	hkvset, hkvs, hkv;
	struct zbx_json		j;
	char			*vault;

	zbx_json_init(&j, 1024);

	while (ZBX_MOCK_END_OF_VECTOR != zbx_mock_vector_element(hvault, &hkvset))
	{
		const char	*path = zbx_mock_get_object_member_string(hkvset, "path");

		zbx_json_addobject(&j, path);

		hkvs = zbx_mock_get_object_member_handle(hkvset, "values");

		while (ZBX_MOCK_END_OF_VECTOR != zbx_mock_vector_element(hkvs, &hkv))
		{
			zbx_json_addstring(&j, zbx_mock_get_object_member_string(hkv, "key"),
					zbx_mock_get_object_member_string(hkv, "value"), ZBX_JSON_TYPE_STRING);
		}

		zbx_json_close(&j);
	}

	vault = zbx_strdup(NULL, j.buffer);
	zbx_json_free(&j);

	return vault;
}

static void	load_um_cache(zbx_um_mock_cache_t *mock_cache, zbx_mock_handle_t hconfig)
{
	zbx_um_mock_cache_t	mock_cache0;
	zbx_dc_config_t		*config;
	zbx_dbsync_t		gmacros, hmacros, htmpls;
	zbx_config_vault_t	config_vault = {NULL, NULL, NULL, NULL, NULL, NULL, NULL};

	const char		*config_source_ip = "";
	char			*config_ssl_ca_location = NULL, *config_ssl_cert_location = NULL,
				*config_ssl_key_location = NULL;

	char			*vault = mock_get_vault(zbx_mock_get_object_member_handle(hconfig, "vault"));

	um_mock_config_init();
	config = get_dc_config();

	um_mock_cache_init(&mock_cache0, -1);
	um_mock_cache_init(mock_cache, hconfig);
	um_mock_cache_dump(mock_cache);

	zbx_dbsync_init(&gmacros, NULL, ZBX_DBSYNC_UPDATE);
	zbx_dbsync_init(&hmacros, NULL, ZBX_DBSYNC_UPDATE);
	zbx_dbsync_init(&htmpls, NULL, ZBX_DBSYNC_UPDATE);

	um_mock_cache_diff(&mock_cache0, mock_cache, &gmacros, &hmacros, &htmpls);
	config->um_cache = um_cache_create();
	config->um_cache = um_cache_sync(config->um_cache, 0, &gmacros, &hmacros, &htmpls, &config_vault,
			get_program_type());

	mock_dbsync_clear(&gmacros);
	mock_dbsync_clear(&hmacros);
	mock_dbsync_clear(&htmpls);

	struct zbx_json_parse	jp;

	if (FAIL == zbx_json_open(vault, &jp))
		fail_msg("invalid vault json");

	zbx_dc_sync_kvs_paths(&jp, &config_vault, config_source_ip, config_ssl_ca_location,
			config_ssl_cert_location, config_ssl_key_location);
	config->um_cache->refcount++;

	zbx_free(vault);
	um_mock_cache_clear(&mock_cache0);
}

void	zbx_mock_test_entry(void **state)
{
	int			ret, expected_ret;
	char			*result = NULL, error[MAX_BUFFER_LEN] = {0};
	const char		*expression;
	zbx_dc_um_handle_t	*um_handle, *um_handle1;
	uint64_t		hostid, um;
	zbx_um_mock_cache_t	mock_cache;

	zbx_mock_handle_t	hconfig = zbx_mock_get_parameter_handle("in.config");

	ZBX_UNUSED(state);

	load_um_cache(&mock_cache, hconfig);

	hostid = zbx_mock_get_parameter_uint64("in.hostid");
	um = zbx_mock_get_parameter_uint64("in.user_macro");

	expression = zbx_mock_get_parameter_string("in.expression");

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	switch (um)
	{
	case 0:
		um_handle = zbx_dc_open_user_macros();
		break;
	case 1:
		um_handle = zbx_dc_open_user_macros_secure();
		break;
	case 2:
		um_handle = zbx_dc_open_user_macros_masked();
		break;
	default:
		exit(1);
		break;
	}

	/* we should also check when user caches overlap */
	um_handle1 = zbx_dc_open_user_macros_secure();

	result = strdup(expression);

	ret = zbx_substitute_macros(&result, error, sizeof(error), macro_resolv_func, hostid, um_handle);

	zbx_dc_close_user_macros(um_handle1);
	zbx_dc_close_user_macros(um_handle);

	zbx_mock_assert_result_eq("zbx_substitute_macros() return code", expected_ret, ret);

	if (SUCCEED == ret)
	{
		const char	*expected_result = zbx_mock_get_parameter_string("out.result");

		zbx_mock_assert_str_eq("zbx_substitute_macros() result", expected_result, result);
	}
	else
	{
		const char	*expected_error = zbx_mock_get_parameter_string("out.error");

		zbx_mock_assert_str_eq("zbx_substitute_macros() expected error", expected_error, error);
	}

	zbx_free(result);
	um_mock_cache_clear(&mock_cache);
}
