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

#include <CUnit/Basic.h>
#include <CUnit/Automated.h>

#define ZBX_CU_MODULE(module)	zbx_cu_init_##module()

extern struct mallinfo	zbx_cu_minfo;

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
