/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#include "zbxmockprimitive.h"
#include "zbxmocktest.h"
#include "zbxmockassert.h"
#include "zbxcommon.h"
#include "zbxalgo.h"


/* read functions */

char	*mock_read_char_ptr(zbx_mock_handle_t handle)
{
	const char		*value;
	zbx_mock_error_t	err;

	if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string(handle, &value)))
		fail_msg("Cannot read string value: %s", zbx_mock_error_string(err));

	return zbx_strdup(NULL, value);
}

const char	*mock_read_const_char_ptr(zbx_mock_handle_t handle)
{
	const char		*value;
	zbx_mock_error_t	err;

	if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string(handle, &value)))
		fail_msg("Cannot read string value: %s", zbx_mock_error_string(err));

	return value;
}

zbx_uint64_t	mock_read_zbx_uint64(zbx_mock_handle_t handle)
{
	zbx_uint64_t		value;
	zbx_mock_error_t	err;

	if (ZBX_MOCK_SUCCESS != (err = zbx_mock_uint64(handle, &value)))
		fail_msg("Cannot read uint64 value: %s", zbx_mock_error_string(err));

	return value;
}

int	mock_read_int(zbx_mock_handle_t handle)
{
	int			value;
	zbx_mock_error_t	err;

	if (ZBX_MOCK_SUCCESS != (err = zbx_mock_int(handle, &value)))
		fail_msg("Cannot read int value: %s", zbx_mock_error_string(err));

	return value;
}

double	mock_read_double(zbx_mock_handle_t handle)
{
	double			value;
	zbx_mock_error_t	err;

	if (ZBX_MOCK_SUCCESS != (err = zbx_mock_float(handle, &value)))
		fail_msg("Cannot read double value: %s", zbx_mock_error_string(err));

	return value;
}

/* clear functions */

void	mock_clear_char_ptr(char **value)
{
	zbx_free(*value);
}

void	mock_clear_const_char_ptr(const char **value)
{
	ZBX_UNUSED(value);
}

void	mock_clear_zbx_uint64(zbx_uint64_t *value)
{
	ZBX_UNUSED(value);
}

void	mock_clear_int(int *value)
{
	ZBX_UNUSED(value);
}

void	mock_clear_double(double *value)
{
	ZBX_UNUSED(value);
}


/* assert_eq functions */


void	mock_assert_eq_char_ptr(const char *prefix, char **v1, char **v2)
{
	zbx_mock_assert_str_eq(prefix, *v1, *v2);
}

void	mock_assert_eq_const_char_ptr(const char *prefix, const char **v1, const char **v2)
{
	zbx_mock_assert_str_eq(prefix, *v1, *v2);
}

void	mock_assert_eq_zbx_uint64(const char *prefix, const zbx_uint64_t *v1, const zbx_uint64_t *v2)
{
	zbx_mock_assert_uint64_eq(prefix, *v1, *v2);
}

void	mock_assert_eq_int(const char *prefix, const int *v1, const int *v2)
{
	zbx_mock_assert_int_eq(prefix, *v1, *v2);
}

void	mock_assert_eq_double(const char *prefix, const double *v1, const double *v2)
{
	zbx_mock_assert_double_eq(prefix, *v1, *v2);
}

/* dump functions */

void	mock_dump_char_ptr(const char *name, char **value, int indent)
{
	printf("%.*s%s: %s\n", indent * 2, MOCK_INDENT, name, *value);
}

void	mock_dump_const_char_ptr(const char *name, const char **value, int indent)
{
	printf("%.*s%s: %s\n", indent * 2, MOCK_INDENT, name, *value);
}

void	mock_dump_zbx_uint64(const char *name, const zbx_uint64_t *value, int indent)
{
	printf("%.*s%s: " ZBX_FS_UI64 "\n", indent * 2, MOCK_INDENT, name, *value);
}

void	mock_dump_int(const char *name, const int *value, int indent)
{
	printf("%.*s%s: %d\n", indent * 2, MOCK_INDENT, name, *value);
}

void	mock_dump_double(const char *name, const double *value, int indent)
{
	printf("%.*s%s: %g\n", indent * 2, MOCK_INDENT, name, *value);
}

