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
#include "usermacros.h"
#define ZBX_DBCONFIG_IMPL
#include "dbconfig.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_mock_test_entry                                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_mock_test_entry(void **state)
{
	char		*returned_params;
	const char	*params, *expected_params;
	ZBX_DC_CONFIG   dc_config = {0};

	ZBX_UNUSED(state);

	config = &dc_config;
	mock_init_macros(config, "in.macros");

	params = zbx_mock_get_parameter_string("in.params");
	expected_params = zbx_mock_get_parameter_string("out.params");

	/* the macro expansion relies on wrapped zbx_hashset_search which returns mocked */
	/* macros when used with global macro index hashset                              */
	returned_params = dc_expand_user_macros_in_func_params(params, 1);
	zbx_mock_assert_str_eq("Expanded parameters", expected_params, returned_params);

	zbx_free(returned_params);

	mock_free_macros();
}
