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

#ifndef ZABBIX_NUM_H
#define ZABBIX_NUM_H

#include "zbxcommon.h"

#define zbx_is_ushort(str, value) \
	zbx_is_uint_n_range(str, ZBX_SIZE_T_MAX, value, sizeof(unsigned short), 0x0, 0xFFFF)

#define zbx_is_uint32(str, value) \
	zbx_is_uint_n_range(str, ZBX_SIZE_T_MAX, value, 4, 0x0, 0xFFFFFFFF)

#define zbx_is_uint64(str, value) \
	zbx_is_uint_n_range(str, ZBX_SIZE_T_MAX, value, 8, 0x0, __UINT64_C(0xFFFFFFFFFFFFFFFF))

#define zbx_is_uint64_n(str, n, value) \
	zbx_is_uint_n_range(str, n, value, 8, 0x0, __UINT64_C(0xFFFFFFFFFFFFFFFF))

#define zbx_is_uint31(str, value) \
	zbx_is_uint_n_range(str, ZBX_SIZE_T_MAX, value, 4, 0x0, 0x7FFFFFFF)

#define ZBX_MAX_UINT31_1	0x7FFFFFFE
#define zbx_is_uint31_1(str, value) \
	zbx_is_uint_n_range(str, ZBX_SIZE_T_MAX, value, 4, 0x0, ZBX_MAX_UINT31_1)

#define zbx_is_uint_range(str, value, min, max) \
	zbx_is_uint_n_range(str, ZBX_SIZE_T_MAX, value, sizeof(unsigned int), min, max)
int	zbx_is_uint_n_range(const char *str, size_t n, void *value, size_t size, zbx_uint64_t min, zbx_uint64_t max);

int	zbx_is_int(const char *str, int *value);

int	zbx_is_hex_n_range(const char *str, size_t n, void *value, size_t size, zbx_uint64_t min, zbx_uint64_t max);
int	zbx_is_hex_string(const char *str);

double	zbx_get_float_epsilon(void);
double	zbx_get_double_epsilon(void);
void	zbx_update_epsilon_to_float_precision(void);
void	zbx_update_epsilon_to_python_compatible_precision(void);
int	zbx_double_compare(double a, double b);
int	zbx_validate_value_dbl(double value);

int	zbx_int_in_list(char *list, int value);

#define ZBX_UNIT_SYMBOLS	"KMGTsmhdw"

#define ZBX_FLAG_DOUBLE_PLAIN	0x00
#define ZBX_FLAG_DOUBLE_SUFFIX	0x01
int	zbx_is_double(const char *str, double *value);

#if defined(_WINDOWS) || defined(__MINGW32__)
int	zbx_wis_uint(const wchar_t *wide_string);
#endif

const char	*zbx_print_double(char *buffer, size_t size, double val);
int		zbx_number_parse(const char *number, int *len);

#define ZBX_STR2UINT64(uint, string) zbx_is_uint64(string, &uint)

int	zbx_str2uint64(const char *str, const char *suffixes, zbx_uint64_t *value);

void	zbx_trim_integer(char *str);
void	zbx_trim_float(char *str);
#endif /* ZABBIX_NUM_H */
