#ifndef ZBXCUNIT_H
#define ZBXCUNIT_H

#include <malloc.h>
#include <CUnit/Basic.h>

#define ZBX_CU_MODULE(module)	zbx_cu_init_##module()

extern struct mallinfo	zbx_cu_minfo;

#define ZBX_CU_LEAK_CHECK_START()	zbx_cu_minfo = mallinfo()
#define ZBX_CU_LEAK_CHECK_END()	{						\
		struct mallinfo minfo_local;					\
		minfo_local = mallinfo(); 					\
		CU_ASSERT_EQUAL(minfo_local.uordblks, zbx_cu_minfo.uordblks);	\
	}


#define ZBX_CU_ASSERT_STRING_EQ(actual, expected) {								\
		CU_assertImplementation(!(strcmp((const char*)(actual), (const char*)(expected))), __LINE__,	\
			zbx_cu_assert_args_str("CU_ASSERT_STRING_EQ", #actual, actual, #expected, expected),	\
			__FILE__, "", CU_FALSE);								\
		}

#define ZBX_CU_ASSERT_STRING_EQ_FATAL(actual, expected) {							\
		CU_assertImplementation(!(strcmp((const char*)(actual), (const char*)(expected)0), __LINE__,	\
			zbx_cu_asssert_args_str("CU_ASSERT_STRING_EQ_FATAL", #actual, actual, #expected, expected),\
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


const char	*zbx_cu_assert_args_str(const char *assert_name, const char *expression1, const char *actual,
		const char *expression2, const char *expected);

const char	*zbx_cu_assert_args_ui64(const char *assert_name, const char *expression1, zbx_uint64_t actual,
		const char *expression2, zbx_uint64_t expected);

const char	*zbx_cu_assert_args_dbl(const char *assert_name, const char *expression1, double actual,
		const char *expression2, double expected);

const char	*zbx_cu_assert_args_int(const char *assert_name, const char *expression1, int actual,
		const char *expression2, int expected);


#endif

