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

#include "zbxnum.h"
#include "zbxcacheconfig/user_macro.h"
#include "um_cache_mock.h"

static void	mock_get_hostids(zbx_vector_uint64_t *hostids, zbx_mock_handle_t handle)
{
	zbx_mock_error_t	err;
	zbx_mock_handle_t	hhostid;
	const char		*hostid_s;
	zbx_uint64_t		hostid;

	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(handle, &hhostid))))
	{
		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string(hhostid, &hostid_s)))
			fail_msg("Cannot read hostid: %s", zbx_mock_error_string(err));

		if (SUCCEED != zbx_is_uint64(hostid_s, &hostid))
			fail_msg("Invalid hostid: %s", hostid_s);

		zbx_vector_uint64_append(hostids, hostid);
	}
}

void	zbx_mock_test_entry(void **state)
{
	zbx_um_mock_cache_t	mock_cache0, mock_cache;
	zbx_dbsync_t		gmacros, hmacros, htmpls;
	zbx_um_cache_t		*cache;
	char			*value = NULL;
	zbx_vector_uint64_t	hostids;
	int			ret;
	zbx_config_vault_t	config_vault = {NULL, NULL, NULL, NULL, NULL, NULL, NULL};

	ZBX_UNUSED(state);

	zbx_vector_uint64_create(&hostids);

	um_mock_config_init();

	um_mock_cache_init(&mock_cache0, -1);
	um_mock_cache_init(&mock_cache, zbx_mock_get_parameter_handle("in.config"));

	zbx_dbsync_init(&gmacros, NULL, ZBX_DBSYNC_UPDATE);
	zbx_dbsync_init(&hmacros, NULL, ZBX_DBSYNC_UPDATE);
	zbx_dbsync_init(&htmpls, NULL, ZBX_DBSYNC_UPDATE);

	um_mock_cache_diff(&mock_cache0, &mock_cache, &gmacros, &hmacros, &htmpls);
	cache = um_cache_create();
	cache = um_cache_sync(cache, 0, &gmacros, &hmacros, &htmpls, &config_vault, get_program_type());

	mock_dbsync_clear(&gmacros);
	mock_dbsync_clear(&hmacros);
	mock_dbsync_clear(&htmpls);

	mock_get_hostids(&hostids, zbx_mock_get_parameter_handle("in.hostids"));

	um_cache_resolve(cache, hostids.values, hostids.values_num, zbx_mock_get_parameter_string("in.macro"),
			ZBX_MACRO_ENV_SECURE, &value);

	ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));

	if (SUCCEED != ret)
	{
		if (NULL != value)
			fail_msg("Expected to resolve nothing while got: %s", value);
	}
	else
	{
		const char	*value_exp;

		value_exp = zbx_mock_get_parameter_string("out.value");

		if (NULL == value)
			fail_msg("Expected to resolve to '%s' while got nothing", value_exp);

		zbx_mock_assert_str_eq("Resolved value", value_exp, value);
		zbx_free(value);
	}

	um_cache_release(cache);
	um_mock_cache_clear(&mock_cache0);
	um_mock_cache_clear(&mock_cache);

	um_mock_config_destroy();

	zbx_vector_uint64_destroy(&hostids);
}
