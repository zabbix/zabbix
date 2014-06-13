#include "zbxcunit.h"

#define ZBX_CU_ASSERT_ARGS_LENGTH	1024
#define ZBX_CU_ASSERT_NAME_LENGTH	128
#define ZBX_CU_ASSERT_BUFFER_SIZE	(ZBX_CU_ASSERT_ARGS_LENGTH * 2 + ZBX_CU_ASSERT_NAME_LENGTH + 16)

struct mallinfo	zbx_cu_minfo;

static char	zbx_cu_assert_args_buffer[ZBX_CU_ASSERT_BUFFER_SIZE];

const char	*zbx_cu_assert_args_str(const char *assert_name, const char *expression1, const char *actual,
		const char *expression2, const char *expected)
{
	size_t	offset = 0;

	offset = zbx_snprintf(zbx_cu_assert_args_buffer, ZBX_CU_ASSERT_NAME_LENGTH + 1, "%s(", assert_name);
	offset += zbx_snprintf(zbx_cu_assert_args_buffer + offset, ZBX_CU_ASSERT_ARGS_LENGTH + 2, "%s=\"%s\", ",
			expression1, actual);
	offset += zbx_snprintf(zbx_cu_assert_args_buffer + offset, ZBX_CU_ASSERT_ARGS_LENGTH + 1, "%s=\"%s\")",
			expression2, expected);

	return zbx_cu_assert_args_buffer;
}

const char	*zbx_cu_assert_args_ui64(const char *assert_name, const char *expression1, zbx_uint64_t actual,
		const char *expression2, zbx_uint64_t expected)
{
	size_t	offset = 0;

	offset = zbx_snprintf(zbx_cu_assert_args_buffer, ZBX_CU_ASSERT_NAME_LENGTH + 1, "%s(", assert_name);
	offset += zbx_snprintf(zbx_cu_assert_args_buffer + offset, ZBX_CU_ASSERT_ARGS_LENGTH + 2, "%s=" ZBX_FS_UI64 ", ",
			expression1, actual);
	offset += zbx_snprintf(zbx_cu_assert_args_buffer + offset, ZBX_CU_ASSERT_ARGS_LENGTH + 1, "%s=" ZBX_FS_UI64  ")",
			expression2, expected);

	return zbx_cu_assert_args_buffer;
}

const char	*zbx_cu_assert_args_dbl(const char *assert_name, const char *expression1, double actual,
		const char *expression2, double expected)
{
	size_t	offset = 0;

	offset = zbx_snprintf(zbx_cu_assert_args_buffer, ZBX_CU_ASSERT_NAME_LENGTH + 1, "%s(", assert_name);
	offset += zbx_snprintf(zbx_cu_assert_args_buffer + offset, ZBX_CU_ASSERT_ARGS_LENGTH + 2, "%s=" ZBX_FS_DBL ", ",
			expression1, actual);
	offset += zbx_snprintf(zbx_cu_assert_args_buffer + offset, ZBX_CU_ASSERT_ARGS_LENGTH + 1, "%s=" ZBX_FS_DBL  ")",
			expression2, expected);

	return zbx_cu_assert_args_buffer;
}

const char	*zbx_cu_assert_args_int(const char *assert_name, const char *expression1, int actual,
		const char *expression2, int expected)
{
	size_t	offset = 0;

	offset = zbx_snprintf(zbx_cu_assert_args_buffer, ZBX_CU_ASSERT_NAME_LENGTH + 1, "%s(", assert_name);
	offset += zbx_snprintf(zbx_cu_assert_args_buffer + offset, ZBX_CU_ASSERT_ARGS_LENGTH + 2, "%s=%d, ",
			expression1, actual);
	offset += zbx_snprintf(zbx_cu_assert_args_buffer + offset, ZBX_CU_ASSERT_ARGS_LENGTH + 1, "%s=%d)",
			expression2, expected);

	return zbx_cu_assert_args_buffer;
}
