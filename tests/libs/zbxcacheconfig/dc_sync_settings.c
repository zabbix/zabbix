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

#include "zbxcacheconfig/dbsync.h"

#include "um_cache_mock.h"

void	zbx_mock_test_entry(void **state)
{
	int		result = SUCCEED;
	zbx_dbsync_t	settings_sync;
	int		exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	int		in_revision = 1;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.revision"))
		in_revision = zbx_mock_get_parameter_int("in.revision");

	zbx_mockdb_init();
	um_mock_config_init();

	zbx_dbsync_init(&settings_sync, NULL, ZBX_DBSYNC_INIT);

	zbx_config_wlock_set_locked();

	if (FAIL == (result = zbx_dbsync_compare_settings(&settings_sync)))
		goto out;

	dc_sync_settings(&settings_sync, in_revision, ZBX_PROGRAM_TYPE_SERVER);

out:
	zbx_dbsync_clear(&settings_sync);

	zbx_config_wlock_set_unlocked();

	zbx_mock_assert_int_eq("return value", exp_result, result);

	/* check if setting revision changed */
	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.revision"))
	{
		int	exp_revision = zbx_mock_get_parameter_int("out.revision");
		int	revision = get_dc_config()->revision.settings_table;

		zbx_mock_assert_int_eq("revision", exp_revision, revision);
	}

	/* validate settings */
	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.config"))
	{
		zbx_config_t		cfg = {0};
		uint64_t		flags = 0;
		zbx_mock_error_t	err;

		zbx_mock_handle_t	configs = zbx_mock_get_parameter_handle("out.config");
		zbx_mock_handle_t	it;

		while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(configs, &it))))
		{
			const char	*setting_name = zbx_mock_get_object_member_string(it, "name");

			if (0 == strcmp(setting_name, "hk_audit_mode"))
				flags |= ZBX_CONFIG_FLAGS_HOUSEKEEPER;
			else if (0 == strcmp(setting_name, "hk_audit"))
				flags |= ZBX_CONFIG_FLAGS_HOUSEKEEPER;
		}

		zbx_config_get(&cfg, flags);

		configs = zbx_mock_get_parameter_handle("out.config");
		it = 0;

		while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(configs, &it))))
		{
			const char	*setting_name = zbx_mock_get_object_member_string(it, "name");

			if (0 == strcmp(setting_name, "hk_audit_mode"))
			{
				int	exp_int = zbx_mock_get_object_member_int(it, "value");

				zbx_mock_assert_int_eq("hk_audit_mode", exp_int, cfg.hk.audit_mode);
			}
			else if (0 == strcmp(setting_name, "hk_audit"))
			{
				int	exp_int = zbx_mock_get_object_member_int(it, "value");

				zbx_mock_assert_int_eq("hk_audit", exp_int, cfg.hk.audit);
			}
		}
	}

	zbx_config_wlock_set_locked();
	um_mock_config_destroy();
	zbx_config_wlock_set_unlocked();
	zbx_mockdb_destroy();
}
