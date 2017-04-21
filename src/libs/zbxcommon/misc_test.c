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

#include "../zbxcunit/zbxcunit.h"

static int	cu_str_init_empty()
{
	return CUE_SUCCESS;
}

static int	cu_str_clean_empty()
{
	return CUE_SUCCESS;
}

static void	check_time_period_test()
{
	char	*period = NULL;
	time_t	time_now = 1492601833;

	period = zbx_strdup(period, "1-5,9:00-18:00");

	CU_ASSERT(SUCCEED == check_time_period(period, time_now));

	zbx_free(period);
}

int	ZBX_CU_MODULE(misc_test)
{
	CU_pSuite	suite = NULL;

	/* test suite: 1 */
	if (NULL == (suite = CU_add_suite("misc_test", cu_str_init_empty, cu_str_clean_empty)))
		return CU_get_error();

	CU_add_test(suite, "check_time_period_test", check_time_period_test);

	return CUE_SUCCESS;
}
