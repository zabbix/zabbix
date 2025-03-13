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

#ifndef ZABBIX_MOCK_PRIMITIVE_H
#define ZABBIX_MOCK_PRIMITIVE_H

#include "zbxcommon.h"
#include "zbxmockdata.h"

#define MOCK_INDENT	"                                                                        "

/* read functions */
char	*mock_read_char_ptr(zbx_mock_handle_t handle);
const char	*mock_read_const_char_ptr(zbx_mock_handle_t handle);
zbx_uint64_t	mock_read_zbx_uint64(zbx_mock_handle_t handle);
int	mock_read_int(zbx_mock_handle_t handle);
double	mock_read_double(zbx_mock_handle_t handle);

/* clear functions */
void	mock_clear_char_ptr(char **value);
void	mock_clear_const_char_ptr(const char **value);
void	mock_clear_zbx_uint64(zbx_uint64_t *value);
void	mock_clear_int(int *value);
void	mock_clear_double(double *value);

/* assert_eq functions */
void	mock_assert_eq_char_ptr(const char *prefix, char **v1, char **v2);
void	mock_assert_eq_const_char_ptr(const char *prefix, const char **v1, const char **v2);
void	mock_assert_eq_zbx_uint64(const char *prefix, const zbx_uint64_t *v1, const zbx_uint64_t *v2);
void	mock_assert_eq_int(const char *prefix, const int *v1, const int *v2);
void	mock_assert_eq_double(const char *prefix, const double *v1, const double *v2);

/* dump functions */
void	mock_dump_char_ptr(const char *name, char **value, int indent);
void	mock_dump_const_char_ptr(const char *name, const char **value, int indent);
void	mock_dump_zbx_uint64(const char *name, const zbx_uint64_t *value, int indent);
void	mock_dump_int(const char *name, const int *value, int indent);
void	mock_dump_double(const char *name, const double *value, int indent);

#endif
