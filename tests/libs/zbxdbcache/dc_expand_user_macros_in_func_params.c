/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "zbxexpression.h"
#include "zbxcommon.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxmutexs.h"
#include "dbconfig.h"

#include "configcache_mock.h"

int	get_process_info_by_thread(int local_server_num, unsigned char *local_process_type, int *local_process_num);

int	get_process_info_by_thread(int local_server_num, unsigned char *local_process_type, int *local_process_num)
{
	ZBX_UNUSED(local_server_num);
	ZBX_UNUSED(local_process_type);
	ZBX_UNUSED(local_process_num);

	return 0;
}

int	MAIN_ZABBIX_ENTRY(int flags)
{
	ZBX_UNUSED(flags);

	return 0;
}

void	zbx_mock_test_entry(void **state)
{
	char		*returned_params;
	const char	*params, *expected_params;

	ZBX_UNUSED(state);

	mock_config_init();
	mock_config_load_user_macros("in.macros");

	params = zbx_mock_get_parameter_string("in.params");
	expected_params = zbx_mock_get_parameter_string("out.params");
	returned_params = zbx_dc_expand_user_macros_in_func_params(params, 1);
	zbx_mock_assert_str_eq("Expanded parameters", expected_params, returned_params);

	zbx_free(returned_params);

	mock_config_free();
}
