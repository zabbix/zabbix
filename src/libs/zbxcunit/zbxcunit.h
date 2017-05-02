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

#include <malloc.h>
#include <CUnit/Basic.h>
#include <CUnit/Automated.h>

#define ZBX_CU_STR2(str)		#str
#define ZBX_CU_STR(str)			ZBX_CU_STR2(str)
#define ZBX_CU_SUITE_PREFIX		zbx_cu_init_
#define ZBX_CU_SUITE_PREFIX_STR		ZBX_CU_STR(ZBX_CU_SUITE_PREFIX)

#define ZBX_CU_DECLARE3(prefix, suite)	prefix ## suite(void)
#define ZBX_CU_DECLARE2(prefix, suite)	ZBX_CU_DECLARE3(prefix, suite)
#define ZBX_CU_DECLARE(suite)		ZBX_CU_DECLARE2(ZBX_CU_SUITE_PREFIX, suite)

typedef int (*zbx_cu_init_suite_func_t)(void);

#define ZBX_CU_LEAK_CHECK_START()	struct mallinfo zbx_cu_minfo = mallinfo()
#define ZBX_CU_LEAK_CHECK_END()	{						\
		struct mallinfo minfo_local;					\
		minfo_local = mallinfo(); 					\
		CU_ASSERT_EQUAL(minfo_local.uordblks, zbx_cu_minfo.uordblks);	\
	}

#define ZBX_CU_ADD_TEST(suite, function)					\
	if (NULL == CU_add_test(suite, #function, function))		\
	{										\
		fprintf(stderr, "Error adding test suite \"" #function "\"\n");		\
		return CU_get_error();							\
	}

#define ZBX_CU_ASSERT_STRING_EQ(actual, expected) {								\
		CU_assertImplementation(!(strcmp((const char*)(actual), (const char*)(expected))), __LINE__,	\
			zbx_cu_assert_args_str("CU_ASSERT_STRING_EQ", #actual, actual, #expected, expected),	\
			__FILE__, "", CU_FALSE);								\
		}

#define ZBX_CU_ASSERT_STRING_EQ_FATAL(actual, expected) {							\
		CU_assertImplementation(!(strcmp((const char*)(actual), (const char*)(expected))), __LINE__,	\
			zbx_cu_assert_args_str("CU_ASSERT_STRING_EQ_FATAL", #actual, actual, #expected, expected),\
			__FILE__, "", CU_TRUE);									\
		}

#define ZBX_CU_ASSERT_STRING_N_EQ(actual, expected, n) {							\
		CU_assertImplementation(!(strncmp((const char*)(actual), (const char*)(expected), n)), __LINE__,\
			zbx_cu_assert_args_str_n("CU_ASSERT_STRING_EQ", #actual, actual, #expected, expected, n),\
			__FILE__, "", CU_FALSE);								\
		}

#define ZBX_CU_ASSERT_STRING_N_EQ_FATAL(actual, expected, n) {							\
		CU_assertImplementation(!(strncmp((const char*)(actual), (const char*)(expected), n)), __LINE__,	\
			zbx_cu_assert_args_str_n("CU_ASSERT_STRING_EQ_FATAL", #actual, actual, #expected, expected, n),\
			__FILE__, "", CU_TRUE);									\
		}

#define ZBX_CU_ASSERT_INT_EQ(actual, expected) {								\
		CU_assertImplementation((actual == expected) , __LINE__,					\
			zbx_cu_assert_args_int("CU_ASSERT_INT_EQ", #actual, actual, #expected, expected),	\
			__FILE__, "", CU_FALSE);								\
		}

#define ZBX_CU_ASSERT_INT_EQ_FATAL(actual, expected) {								\
		CU_assertImplementation((actual == expected) , __LINE__,					\
			zbx_cu_assert_args_int("CU_ASSERT_INT_EQ_FATAL", #actual, actual, #expected, expected),	\
			__FILE__, "", CU_TRUE);								\
		}

#define ZBX_CU_ASSERT_INT_NE(actual, expected) {								\
		CU_assertImplementation((actual != expected) , __LINE__,					\
			zbx_cu_assert_args_int("CU_ASSERT_INT_NE", #actual, actual, #expected, expected),	\
			__FILE__, "", CU_FALSE);								\
		}

#define ZBX_CU_ASSERT_INT_NE_FATAL(actual, expected) {								\
		CU_assertImplementation((actual != expected) , __LINE__,					\
			zbx_cu_assert_args_int("CU_ASSERT_INT_NE_FATAL", #actual, actual, #expected, expected),	\
			__FILE__, "", CU_TRUE);								\
		}

#define ZBX_CU_ASSERT_UINT64_EQ(actual, expected) {								\
		CU_assertImplementation((actual == expected) , __LINE__,					\
			zbx_cu_assert_args_ui64("CU_ASSERT_UINT64_EQ", #actual, actual, #expected, expected),	\
			__FILE__, "", CU_FALSE);								\
		}

#define ZBX_CU_ASSERT_UINT64_EQ_FATAL(actual, expected) {							\
		CU_assertImplementation((actual == expected) , __LINE__,					\
			zbx_cu_assert_args_ui64("CU_ASSERT_UINT64_EQ_FATAL", #actual, actual, #expected, expected),\
			__FILE__, "", CU_TRUE);								\
		}


#define ZBX_CU_ASSERT_DOUBLE_EQ(actual, expected) {								\
		CU_assertImplementation((actual == expected) , __LINE__,					\
			zbx_cu_assert_args_dbl("CU_ASSERT_DOUBLE_EQ", #actual, actual, #expected, expected),	\
			__FILE__, "", CU_FALSE);								\
		}

#define ZBX_CU_ASSERT_DOUBLE_EQ_FATAL(actual, expected) {							\
		CU_assertImplementation((actual == expected) , __LINE__,					\
			zbx_cu_assert_args_dbl("CU_ASSERT_DOUBLE_EQ_FATAL", #actual, actual, #expected, expected),\
			__FILE__, "", CU_FALSE);								\
		}

#define ZBX_CU_ASSERT_CHAR_EQ(actual, expected) {								\
		CU_assertImplementation((actual == expected) , __LINE__,					\
			zbx_cu_assert_args_char("CU_ASSERT_CHAR_EQ", #actual, actual, #expected, expected),	\
			__FILE__, "", CU_FALSE);								\
		}

#define ZBX_CU_ASSERT_CHAR_EQ_FATAL(actual, expected) {								\
		CU_assertImplementation((actual == expected) , __LINE__,					\
			zbx_cu_assert_args_char("CU_ASSERT_CHAR_EQ_FATAL", #actual, actual, #expected, expected),\
			__FILE__, "", CU_TRUE);								\
		}

#define ZBX_CU_ASSERT_CHAR_NE(actual, expected) {								\
		CU_assertImplementation((actual != expected) , __LINE__,					\
			zbx_cu_assert_args_char("CU_ASSERT_CHAR_NE", #actual, actual, #expected, expected),	\
			__FILE__, "", CU_FALSE);								\
		}

#define ZBX_CU_ASSERT_CHAR_NE_FATAL(actual, expected) {								\
		CU_assertImplementation((actual != expected) , __LINE__,					\
			zbx_cu_assert_args_char("CU_ASSERT_CHAR_NE_FATAL", #actual, actual, #expected, expected),\
			__FILE__, "", CU_TRUE);								\
		}


const char	*zbx_cu_assert_args_str(const char *assert_name, const char *expression1, const char *actual,
		const char *expression2, const char *expected);

const char	*zbx_cu_assert_args_str_n(const char *assert_name, const char *expression1, const char *actual,
		const char *expression2, const char *expected, size_t n);

const char	*zbx_cu_assert_args_ui64(const char *assert_name, const char *expression1, zbx_uint64_t actual,
		const char *expression2, zbx_uint64_t expected);

const char	*zbx_cu_assert_args_dbl(const char *assert_name, const char *expression1, double actual,
		const char *expression2, double expected);

const char	*zbx_cu_assert_args_int(const char *assert_name, const char *expression1, int actual,
		const char *expression2, int expected);

const char	*zbx_cu_assert_args_char(const char *assert_name, const char *expression1, char actual,
		const char *expression2, char expected);

extern struct mallinfo	zbx_cu_minfo;

void	zbx_cu_run(int args, char *argv[]);

#endif

#endif
