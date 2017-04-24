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

#ifndef ZABBIX_ZBXCUNIT_H
#define ZABBIX_ZBXCUNIT_H

#ifdef ZBX_CUNIT

#include <CUnit/Basic.h>
#include <CUnit/Automated.h>

#define ZBX_CU_STR2(str)		#str
#define ZBX_CU_STR(str)			ZBX_CU_STR2(str)
#define ZBX_CU_SUITE_PREFIX		zbx_cu_init_
#define ZBX_CU_SUITE_PREFIX_STR		ZBX_CU_STR(ZBX_CU_SUITE_PREFIX)

#define ZBX_CU_SUITE3(prefix, suite)	prefix ## suite(void)
#define ZBX_CU_SUITE2(prefix, suite)	ZBX_CU_SUITE3(prefix, suite)
#define ZBX_CU_SUITE(suite)		ZBX_CU_SUITE2(ZBX_CU_SUITE_PREFIX, suite)

typedef int (*zbx_cu_init_suite_func_t)(void);

#define ZBX_CU_LEAK_CHECK_START()	zbx_cu_minfo = mallinfo()
#define ZBX_CU_LEAK_CHECK_END()	{						\
		struct mallinfo minfo_local;					\
		minfo_local = mallinfo(); 					\
		CU_ASSERT_EQUAL(minfo_local.uordblks, zbx_cu_minfo.uordblks);	\
	}

#define ZBX_CU_ADD_TEST(suite, description, function)					\
	if (NULL == CU_add_test(suite, #function "(): " description, function))		\
	{										\
		fprintf(stderr, "Error adding test suite \"%s\"\n", description);	\
		return CU_get_error();							\
	}

#endif


#include <malloc.h>
static struct mallinfo	zbx_cu_minfo;

void	zbx_cu_run(int args, char *argv[]);

#endif
