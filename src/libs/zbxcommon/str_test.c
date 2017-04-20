/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#include "zbxcunit.h"

static int	cu_str_init_empty()
{
	return CUE_SUCCESS;
}

static int	cu_str_clean_empty()
{
	return CUE_SUCCESS;
}

static void	zbx_user_macro_parse_test()
{
	int macro_r, context_l, context_r, ret;

	CU_ASSERT(SUCCEED == (ret = zbx_user_macro_parse("{$MODULE}", &macro_r, &context_l, &context_r)));
}

int	ZBX_CU_MODULE(str_test)
{
	CU_pSuite	suite = NULL;

	/* test suite: str 1 */
	if (NULL == (suite = CU_add_suite("str_test", cu_str_init_empty, cu_str_clean_empty)))
		return CU_get_error();

	CU_add_test(suite, "zbx_user_macro_parse_test", zbx_user_macro_parse_test);

	return CUE_SUCCESS;
}
